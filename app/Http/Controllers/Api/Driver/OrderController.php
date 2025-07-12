<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Jobs\FindRandomDriverForOrder;
use App\Models\Driver;
use App\Models\Order;
use App\Notifications\DriverAcceptedOrder;
use App\Notifications\DriverDeclinedOrder;
use App\Notifications\OrderHasBeenComplete;
use App\Notifications\WaitForDriverConfirmation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
  
    public function summary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'status' => 'nullable|numeric'
        ]);

        if ($validator->fails()) {
            return response([
                'error' => true,
                'message' => $validator->messages()
            ], 422);
        }

        $summary = Order::whereDate('created_at', '>=', $request['from'])
            ->whereDate('created_at', '<=', $request['to'])
            ->where('driver_id', auth('driver')->id())
            ->when($request['status'], function ($query) use ($request) {
                $query->where('status_code', $request['status']);
            })->get();

        return response([
            'data' => $summary,
        ]);
    }

   
    public function acceptOrder(Request $request, Order $order)
    {
        if (!in_array(
            $order->status_code,
            [
                config('const.order.status.pending'),
                config('const.order.status.cancled_by_driver')
            ]
        )) {
            return response()->json([
                'error' => true,
                'message' => ['Không thể cập nhật trạng thái đơn hàng']
            ], 422);
        }

        if (!empty($order->driver_id) && $order->driver_id != auth('driver')->id()) {
            return response([
                'error' => true,
                'message' => 'Unauthorized'
            ], 401);
        }

        $order->update([
            'driver_id' => auth('driver')->id(),
            'driver_accept_at' => now(),
            'status_code' => config('const.order.status.inprocess')
        ]);

        auth('driver')->user()->update([
            'delivering_order_id' => $order->id,
            'status' => config('const.driver.status.busy')
        ]);

        // notify user
        $order->customer->notify(new DriverAcceptedOrder($order));

        return response()->json([
            'data' => $order->refresh()
        ]);
    }

    public function declineOrder(Request $request, Order $order)
    {
        if ($order->status_code != config('const.order.status.pending')) {
            return response()->json([
                'error' => true,
                'message' => ['Không thể cập nhật trạng thái đơn hàng']
            ], 422);
        }

        if (!empty($order->driver_id) && $order->driver_id != auth('driver')->id()) {
            return response([
                'error' => true,
                'message' => 'Unauthorized'
            ], 401);
        }

        $exceptDriverList = $order->except_drivers;

        $exceptDriverList[] = auth('driver')->id();

        $order->update([
            'except_drivers' => $exceptDriverList
        ]);

        // notify user
        if (!empty($order->driver_id)) {
            $order->customer->notify(new DriverDeclinedOrder($order));
            $order->update([
                'status_code' => config('const.order.status.cancled_by_driver'),
                'driver_id' => null
            ]);
        } else {
            dispatch(new FindRandomDriverForOrder($order));
        }

        return response()->json([
            'data' => $order->refresh()
        ]);
    }

    public function conpleteOrder(Request $request, Order $order)
    {
        if ($order->status_code != config('const.order.status.inprocess')) {
            return response()->json([
                'error' => true,
                'message' => ['Không thể cập nhật trạng thái đơn hàng']
            ], 422);
        }

        if ($order->driver_id != auth('driver')->id()) {
            return response([
                'error' => true,
                'message' => 'Unauthorized'
            ], 401);
        }

        $order->update([
            'status_code' => config('const.order.status.completed')
        ]);

        auth('driver')->user()->update([
            'delivering_order_id' => null,
            'status' => config('const.driver.status.free')
        ]);

        $order->customer->notify(new OrderHasBeenComplete($order));

        return response()->json([
            'data' => $order->refresh()
        ]);
    }

    public function detail(Request $request, Order $order)
    {
        return $order;
    }

    public function orderSharing(Request $request, Order $order)
    {
        if (!in_array(
            $order->status_code,
            [
                config('const.order.status.pending'),
                config('const.order.status.cancled_by_driver')
            ]
        )) {
            return response()->json([
                'error' => true,
                'message' => ['Đơn hàng không thể cập nhật lại tài xế']
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'driver_id' => ['bail', 'required', 'exists:drivers,id', function ($attr, $val, $fail) {
                if (Driver::find($val)->status != config('const.driver.status.free')) {
                    $fail('Tài xế hiện đang không sẵn sàng');
                }
                if (!Driver::find($val)->profile) {
                    $fail('Tài xế hiện đang không sẵn sàng');
                }
            }]
        ]);

        if ($validator->fails()) {
            return response([
                'error' => true,
                'message' => $validator->messages()
            ], 422);
        }

        if (now()->diffInMinutes($order->created_at) > 60) {
            return response([
                'error' => true,
                'message' => [
                    'clock' => [
                        'Quá thời gian'
                    ]
                ]
            ], 422);
        }

        $exceptDriverList = $order->except_drivers;

        $exceptDriverList[] = auth('driver')->id();

        $order->update([
            'except_drivers' => $exceptDriverList,
            'is_sharable' => false

        ]);

        Driver::find($request['driver_id'])->notify(new WaitForDriverConfirmation($order));

        return response()->json([
            'data' => $order->refresh()
        ]);
    }

    public function acceptOrderSharing(Request $request, Order $order)
    {
        if (!in_array(
            $order->status_code,
            [
                config('const.order.status.pending'),
                config('const.order.status.cancled_by_driver')
            ]
        )) {
            return response()->json([
                'error' => true,
                'message' => ['Không thể cập nhật trạng thái đơn hàng']
            ], 422);
        }

        $order->update([
            'driver_id' => auth('driver')->id(),
            'driver_accept_at' => now(),
            'status_code' => config('const.order.status.inprocess')
        ]);

        auth('driver')->user()->update([
            'delivering_order_id' => $order->id,
            'status' => config('const.driver.status.busy')
        ]);

        // notify user
        $order->customer->notify(new DriverAcceptedOrder($order));

        return response()->json([
            'data' => $order->refresh()
        ]);
    }

    public function declineOrderSharing(Request $request, Order $order)
    {
        if ($order->status_code != config('const.order.status.pending')) {
            return response()->json([
                'error' => true,
                'message' => ['Không thể cập nhật trạng thái đơn hàng']
            ], 422);
        }

        $order->update([
            'is_sharable' => true
        ]);

        dispatch(new FindRandomDriverForOrder($order));

        return response()->json([
            'data' => $order->refresh()
        ]);
    }

    
    public function getMyOrders(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'nullable|integer|in:1,2,3,4',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response([
                'error' => true,
                'message' => $validator->messages()
            ], 422);
        }

        $query = Order::where('driver_id', auth('driver')->id())
            ->with(['customer', 'driver'])
            ->latest();

        // Lọc theo trạng thái nếu có
        if ($request->has('status')) {
            $query->where('status_code', $request->status);
        }

        $perPage = $request->get('per_page', 15);
        $orders = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    public function getInProcessOrders(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response([
                'error' => true,
                'message' => $validator->messages()
            ], 422);
        }

        $perPage = $request->get('per_page', 15);
        $orders = Order::where('driver_id', auth('driver')->id())
            ->where('status_code', config('const.order.status.inprocess', 2))
            ->with(['customer', 'driver'])
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

   
    public function getCompletedOrders(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response([
                'error' => true,
                'message' => $validator->messages()
            ], 422);
        }

        $perPage = $request->get('per_page', 15);
        $orders = Order::where('driver_id', auth('driver')->id())
            ->where('status_code', config('const.order.status.completed', 3))
            ->with(['customer', 'driver'])
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

     
    public function getAvailableOrders(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'radius' => 'nullable|numeric|min:0.1|max:50',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response([
                'error' => true,
                'message' => $validator->messages()
            ], 422);
        }

        $driverId = auth('driver')->id();
        $query = Order::whereNull('driver_id')
            ->where('status_code', config('const.order.status.pending', 1))
            ->whereNotIn('id', function($subQuery) use ($driverId) {
                $subQuery->select('delivering_order_id')
                    ->from('drivers')
                    ->where('id', $driverId)
                    ->whereNotNull('delivering_order_id');
            });

        // Loại bỏ đơn hàng mà driver đã từ chối
        $query->whereRaw('NOT JSON_CONTAINS(except_drivers, ?)', [$driverId]);

        // Nếu có tọa độ, tính khoảng cách và sắp xếp theo khoảng cách
        if ($request->has('latitude') && $request->has('longitude')) {
            $lat = $request->latitude;
            $lon = $request->longitude;
            $radius = $request->get('radius', 5.0);

            $query->selectRaw("
                    *,
                    6371 * acos(
                        cos( radians(?) ) *
                        cos( radians( JSON_EXTRACT(from_address, '$.lat') ) ) *
                        cos( radians( JSON_EXTRACT(from_address, '$.lon') ) - radians(?) ) +
                        sin( radians(?) ) *
                        sin( radians( JSON_EXTRACT(from_address, '$.lat') ) )
                    ) as distance
                ", [$lat, $lon, $lat])
                ->having('distance', '<=', $radius)
                ->orderBy('distance')
                ->orderBy('created_at', 'asc');
        } else {
            $query->orderBy('created_at', 'asc');
        }

        $perPage = $request->get('per_page', 15);
        $orders = $query->with(['customer'])
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    public function arrivedAtDestination(Request $request, Order $order)
    {
        // Kiểm tra quyền truy cập
        if ($order->driver_id != auth('driver')->id()) {
            return response([
                'error' => true,
                'message' => 'Unauthorized'
            ], 401);
        }

        // Kiểm tra trạng thái đơn hàng
        if ($order->status_code != config('const.order.status.inprocess', 2)) {
            return response()->json([
                'error' => true,
                'message' => ['Đơn hàng phải đang trong trạng thái đang xử lý']
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'note' => 'nullable|string|max:1000',
            'description' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response([
                'error' => true,
                'message' => $validator->messages()
            ], 422);
        }

        try {
            // Cập nhật trạng thái đơn hàng
            $order->update([
                'status_code' => 3, // Đã tới địa điểm giao
                'completed_at' => now()
            ]);

            // Thêm dữ liệu vào bảng tracker
            $trackerData = [
                'order_id' => $order->id,
                'status' => 3, // Đã tới địa điểm giao
                'note' => $request->get('note'),
                'description' => $request->get('description', [
                    'action' => 'arrived_at_destination',
                    'timestamp' => now()->toISOString(),
                    'driver_id' => auth('driver')->id(),
                    'driver_name' => auth('driver')->user()->name,
                    'location' => auth('driver')->user()->current_location ?? null
                ])
            ];

            \App\Models\Tracker::create($trackerData);

            // Gửi thông báo cho customer
            $order->customer->notify(new \App\Notifications\DriverArrivedAtDestination($order));

            return response()->json([
                'success' => true,
                'message' => 'Đã cập nhật trạng thái đã tới địa điểm giao hàng',
                'data' => $order->refresh()
            ]);

        } catch (\Exception $e) {
            \Log::error('Error updating order status to arrived: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'driver_id' => auth('driver')->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => true,
                'message' => 'Có lỗi xảy ra khi cập nhật trạng thái đơn hàng'
            ], 500);
        }
    }

    public function getActiveOrders(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response([
                'error' => true,
                'message' => $validator->messages()
            ], 422);
        }

        $perPage = $request->get('per_page', 15);
        $orders = Order::where('driver_id', auth('driver')->id())
            ->where('status_code', 2)
            ->with(['customer', 'driver'])
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }


    public function getCompletedOrdersCustom(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response([
                'error' => true,
                'message' => $validator->messages()
            ], 422);
        }

        $perPage = $request->get('per_page', 15);
        $orders = Order::where('driver_id', auth('driver')->id())
            ->where('status_code', 4)
            ->with(['customer', 'driver'])
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    public function getCancelledOrdersCustom(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response([
                'error' => true,
                'message' => $validator->messages()
            ], 422);
        }

        $perPage = $request->get('per_page', 15);
        $orders = Order::where('driver_id', auth('driver')->id())
            ->where('status_code', 5)
            ->with(['customer', 'driver'])
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    public function getArrivingOrdersCustom(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response([
                'error' => true,
                'message' => $validator->messages()
            ], 422);
        }

        $perPage = $request->get('per_page', 15);
        $orders = Order::where('driver_id', auth('driver')->id())
            ->where('status_code', 3)
            ->with(['customer', 'driver'])
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    /**
     * Lấy lịch sử giao hàng cho shipper
     * GET /api/driver/orders/delivery-history
     */
    public function getDeliveryHistory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'status' => 'nullable|integer|in:1,2,3,4,5',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'include_stats' => 'nullable|in:true,false,0,1'
        ]);

        if ($validator->fails()) {
            return response([
                'error' => true,
                'message' => $validator->messages()
            ], 422);
        }

        $driverId = auth('driver')->id();
        $query = Order::where('driver_id', $driverId)
            ->with(['customer', 'driver', 'tracker']);

        // Lọc theo khoảng thời gian nếu có
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        // Lọc theo trạng thái nếu có
        if ($request->has('status')) {
            $query->where('status_code', $request->status);
        }

        $perPage = $request->get('per_page', 15);
        $orders = $query->latest()->paginate($perPage);

        // Thêm thống kê nếu yêu cầu
        $stats = null;
        $includeStats = $request->get('include_stats', false);
        if ($includeStats === 'true' || $includeStats === true || $includeStats === '1' || $includeStats === 1) {
            $stats = $this->getDeliveryStats($driverId, $request->from_date, $request->to_date);
        }

        $response = [
            'success' => true,
            'data' => $orders
        ];

        if ($stats) {
            $response['statistics'] = $stats;
        }

        return response()->json($response);
    }

    /**
     * Lấy thống kê giao hàng
     */
    private function getDeliveryStats($driverId, $fromDate = null, $toDate = null)
    {
        $query = Order::where('driver_id', $driverId);

        if ($fromDate) {
            $query->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        $totalOrders = $query->count();
        $completedOrders = $query->where('status_code', 4)->count();
        $cancelledOrders = $query->where('status_code', 5)->count();
        $totalEarnings = $query->where('status_code', 4)->sum('shipping_cost');
        $averageRating = $query->whereNotNull('driver_rate')->avg('driver_rate');
        $totalDistance = $query->where('status_code', 4)->sum('distance');

        // Thống kê theo ngày trong tuần
        $dailyStats = $query->selectRaw('
                DAYOFWEEK(created_at) as day_of_week,
                COUNT(*) as total_orders,
                SUM(CASE WHEN status_code = 4 THEN 1 ELSE 0 END) as completed_orders,
                SUM(CASE WHEN status_code = 4 THEN shipping_cost ELSE 0 END) as earnings,
                AVG(CASE WHEN status_code = 4 THEN distance ELSE NULL END) as avg_distance
            ')
            ->groupBy('day_of_week')
            ->get();

        // Thống kê theo tháng
        $monthlyStats = $query->selectRaw('
                YEAR(created_at) as year,
                MONTH(created_at) as month,
                COUNT(*) as total_orders,
                SUM(CASE WHEN status_code = 4 THEN 1 ELSE 0 END) as completed_orders,
                SUM(CASE WHEN status_code = 4 THEN shipping_cost ELSE 0 END) as earnings
            ')
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->limit(12)
            ->get();

        return [
            'overview' => [
                'total_orders' => $totalOrders,
                'completed_orders' => $completedOrders,
                'cancelled_orders' => $cancelledOrders,
                'completion_rate' => $totalOrders > 0 ? round(($completedOrders / $totalOrders) * 100, 2) : 0,
                'total_earnings' => $totalEarnings,
                'average_rating' => round($averageRating, 2),
                'total_distance_km' => round($totalDistance, 2),
                'average_distance_per_order' => $completedOrders > 0 ? round($totalDistance / $completedOrders, 2) : 0
            ],
            'daily_stats' => $dailyStats,
            'monthly_stats' => $monthlyStats
        ];
    }

    /**
     * Lấy chi tiết lịch sử giao hàng của một đơn hàng cụ thể
     * GET /api/driver/orders/{order}/delivery-details
     */
    public function getDeliveryDetails(Request $request, Order $order)
    {
        // Kiểm tra quyền truy cập
        if ($order->driver_id != auth('driver')->id()) {
            return response([
                'error' => true,
                'message' => 'Unauthorized'
            ], 401);
        }

        // Lấy thông tin tracker của đơn hàng
        $trackers = $order->tracker()->orderBy('created_at')->get();

        // Tính toán thời gian giao hàng
        $deliveryTime = null;
        if ($order->driver_accept_at && $order->completed_at) {
            $deliveryTime = $order->completed_at->diffInMinutes($order->driver_accept_at);
        }

        // Thông tin chi tiết
        $deliveryDetails = [
            'order_info' => [
                'id' => $order->id,
                'status_code' => $order->status_code,
                'created_at' => $order->created_at,
                'driver_accept_at' => $order->driver_accept_at,
                'completed_at' => $order->completed_at,
                'delivery_time_minutes' => $deliveryTime,
                'shipping_cost' => $order->shipping_cost,
                'distance' => $order->distance,
                'driver_rate' => $order->driver_rate,
                'driver_note' => $order->driver_note
            ],
            'customer_info' => [
                'id' => $order->customer->id,
                'name' => $order->customer->name,
                'phone' => $order->customer->phone,
                'avatar' => $order->customer->avatar
            ],
            'addresses' => [
                'from_address' => $order->from_address,
                'to_address' => $order->to_address
            ],
            'items' => $order->items,
            'tracking_history' => $trackers->map(function($tracker) {
                return [
                    'id' => $tracker->id,
                    'status' => $tracker->status,
                    'note' => $tracker->note,
                    'description' => $tracker->description,
                    'created_at' => $tracker->created_at
                ];
            }),
            'proof_images' => $order->proofImages ?? []
        ];

        return response()->json([
            'success' => true,
            'data' => $deliveryDetails
        ]);
    }
}
