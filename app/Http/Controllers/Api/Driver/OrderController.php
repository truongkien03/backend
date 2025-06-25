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
}
