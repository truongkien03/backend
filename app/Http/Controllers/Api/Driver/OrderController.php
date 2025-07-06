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
    /**
     * @OA\Get(
     *      path="/driver/orders/summary",
     *      operationId="summary",
     *      tags={"driver"},
     *      summary="",
     *      description="",
     *      @OA\Response(response=200,description="successful operation", @OA\JsonContent()),
     *      @OA\Response(response=422, description="Bad request"),
     *      @OA\Response(response=500, description="Server error"),
     *      security={
     *          {"bearerAuth": {}}
     *      }
     *     )
     */
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

    /**
     * @OA\Post(
     *      path="/driver/orders/{orderId}/accept",
     *      operationId="acceptOrder",
     *      tags={"driver"},
     *      summary="",
     *      description="",
     *      @OA\Response(response=200,description="successful operation", @OA\JsonContent()),
     *      @OA\Response(response=422, description="Bad request"),
     *      @OA\Response(response=500, description="Server error"),
     *      security={
     *          {"bearerAuth": {}}
     *      }
     *     )
     */
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

    /**
     * @OA\Post(
     *      path="/driver/orders/{orderId}/decline",
     *      operationId="declineOrder",
     *      tags={"driver"},
     *      summary="",
     *      description="",
     *      @OA\Response(response=200,description="successful operation", @OA\JsonContent()),
     *      @OA\Response(response=422, description="Bad request"),
     *      @OA\Response(response=500, description="Server error"),
     *      security={
     *          {"bearerAuth": {}}
     *      }
     *     )
     */
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

    /**
     * @OA\Get(
     *      path="/driver/orders/my-orders",
     *      operationId="getMyOrders",
     *      tags={"driver"},
     *      summary="Lấy danh sách đơn hàng của driver",
     *      description="Lấy tất cả đơn hàng mà driver đã nhận (có driver_id = driver hiện tại)",
     *      @OA\Parameter(
     *          name="status",
     *          required=false,
     *          in="query",
     *          description="Trạng thái đơn hàng (1: pending, 2: inprocess, 3: completed, 4: cancled_by_driver)",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="page",
     *          required=false,
     *          in="query",
     *          description="Số trang",
     *          @OA\Schema(type="integer", default=1)
     *      ),
     *      @OA\Parameter(
     *          name="per_page",
     *          required=false,
     *          in="query",
     *          description="Số đơn hàng mỗi trang",
     *          @OA\Schema(type="integer", default=15)
     *      ),
     *      @OA\Response(response=200,description="successful operation", @OA\JsonContent()),
     *      @OA\Response(response=422, description="Bad request"),
     *      @OA\Response(response=500, description="Server error"),
     *      security={
     *          {"bearerAuth": {}}
     *      }
     *     )
     */
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

    /**
     * @OA\Get(
     *      path="/driver/orders/inprocess",
     *      operationId="getInProcessOrders",
     *      tags={"driver"},
     *      summary="Lấy danh sách đơn hàng đang xử lý",
     *      description="Lấy đơn hàng đang xử lý của driver (status_code = 2)",
     *      @OA\Parameter(
     *          name="page",
     *          required=false,
     *          in="query",
     *          description="Số trang",
     *          @OA\Schema(type="integer", default=1)
     *      ),
     *      @OA\Parameter(
     *          name="per_page",
     *          required=false,
     *          in="query",
     *          description="Số đơn hàng mỗi trang", 
     *          @OA\Schema(type="integer", default=15)
     *      ),
     *      @OA\Response(response=200,description="successful operation", @OA\JsonContent()),
     *      @OA\Response(response=422, description="Bad request"),
     *      @OA\Response(response=500, description="Server error"),
     *      security={
     *          {"bearerAuth": {}}
     *      }
     *     )
     */
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

    /**
     * @OA\Get(
     *      path="/driver/orders/completed",
     *      operationId="getCompletedOrders", 
     *      tags={"driver"},
     *      summary="Lấy danh sách đơn hàng đã hoàn thành",
     *      description="Lấy đơn hàng đã hoàn thành của driver (status_code = 3)",
     *      @OA\Parameter(
     *          name="page",
     *          required=false,
     *          in="query",
     *          description="Số trang",
     *          @OA\Schema(type="integer", default=1)
     *      ),
     *      @OA\Parameter(
     *          name="per_page",
     *          required=false,
     *          in="query",
     *          description="Số đơn hàng mỗi trang",
     *          @OA\Schema(type="integer", default=15)
     *      ),
     *      @OA\Response(response=200,description="successful operation", @OA\JsonContent()),
     *      @OA\Response(response=422, description="Bad request"),
     *      @OA\Response(response=500, description="Server error"),
     *      security={
     *          {"bearerAuth": {}}
     *      }
     *     )
     */
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

    /**
     * @OA\Get(
     *      path="/driver/orders/available",
     *      operationId="getAvailableOrders",
     *      tags={"driver"},
     *      summary="Lấy danh sách đơn hàng có sẵn để nhận",
     *      description="Lấy đơn hàng chưa có driver nhận (driver_id = null) và chưa từ chối",
     *      @OA\Parameter(
     *          name="latitude",
     *          required=false,
     *          in="query",
     *          description="Vĩ độ hiện tại của driver",
     *          @OA\Schema(type="number", format="float")
     *      ),
     *      @OA\Parameter(
     *          name="longitude", 
     *          required=false,
     *          in="query",
     *          description="Kinh độ hiện tại của driver",
     *          @OA\Schema(type="number", format="float")
     *      ),
     *      @OA\Parameter(
     *          name="radius",
     *          required=false,
     *          in="query",
     *          description="Bán kính tìm kiếm (km)",
     *          @OA\Schema(type="number", format="float", default=5.0)
     *      ),
     *      @OA\Parameter(
     *          name="page",
     *          required=false,
     *          in="query",
     *          description="Số trang",
     *          @OA\Schema(type="integer", default=1)
     *      ),
     *      @OA\Parameter(
     *          name="per_page",
     *          required=false,
     *          in="query",
     *          description="Số đơn hàng mỗi trang",
     *          @OA\Schema(type="integer", default=15)
     *      ),
     *      @OA\Response(response=200,description="successful operation", @OA\JsonContent()),
     *      @OA\Response(response=422, description="Bad request"),
     *      @OA\Response(response=500, description="Server error"),
     *      security={
     *          {"bearerAuth": {}}
     *      }
     *     )
     */
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

    /**
     * @OA\Post(
     *      path="/driver/orders/{orderId}/arrived",
     *      operationId="arrivedAtDestination",
     *      tags={"driver"},
     *      summary="Cập nhật trạng thái đã tới địa điểm giao hàng",
     *      description="Cập nhật status_code thành 3 (đã tới địa điểm giao) và thêm dữ liệu vào bảng tracker",
     *      @OA\Parameter(
     *          name="orderId",
     *          required=true,
     *          in="path",
     *          description="ID của đơn hàng",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\RequestBody(
     *          required=false,
     *          @OA\JsonContent(
     *              @OA\Property(property="note", type="string", description="Ghi chú của driver"),
     *              @OA\Property(property="description", type="object", description="Mô tả chi tiết (tùy chọn)")
     *          )
     *      ),
     *      @OA\Response(response=200,description="successful operation", @OA\JsonContent()),
     *      @OA\Response(response=422, description="Bad request"),
     *      @OA\Response(response=500, description="Server error"),
     *      security={
     *          {"bearerAuth": {}}
     *      }
     *     )
     */
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
}
