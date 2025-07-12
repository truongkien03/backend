<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OrderProofImage;
use Illuminate\Support\Facades\Validator;
use App\Notifications\OrderHasBeenComplete;
use App\Services\FcmV1Service;

class OrderProofImageController extends Controller
{

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,id',
            'image' => 'required|file|image|mimes:jpeg,png,jpg,gif|max:10240', // 10MB
            'note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->messages()
            ], 422);
        }

        // Đảm bảo thư mục tồn tại
        $folder = public_path('image/order_proof_images');
        if (!file_exists($folder)) {
            mkdir($folder, 0777, true);
        }

        // Lưu file ảnh
        $file = $request->file('image');
        $fileName = uniqid('proof_') . '.' . $file->getClientOriginalExtension();
        $file->move($folder, $fileName);
        $imageUrl = 'image/order_proof_images/' . $fileName;

        $proof = \App\Models\OrderProofImage::create([
            'order_id' => $request->order_id,
            'shipper_id' => auth('driver')->id(),
            'image_url' => $imageUrl,
            'note' => $request->note,
        ]);

        // Cập nhật trạng thái đơn hàng từ 3 thành 4
        $order = \App\Models\Order::find($request->order_id);
        if ($order && $order->driver_id == auth('driver')->id()) {
            $order->update([
                'status_code' => 4
            ]);
            // Gửi thông báo cho khách hàng và shipper qua FCM thủ công
            $fcmService = app(FcmV1Service::class);
            $title = 'Đơn hàng đã hoàn thành';
            $body = 'Đơn hàng của bạn đã được giao thành công. Cảm ơn bạn đã sử dụng dịch vụ!';
            $data = [
                'type' => 'order_completed',
                'order_id' => (string)$order->id,
                'screen' => 'order_detail',
                'timestamp' => now()->toISOString(),
            ];
            // Gửi cho khách hàng
            $customerTokens = $order->customer->fcm_token;
            if ($customerTokens) {
                if (is_array($customerTokens)) {
                    foreach ($customerTokens as $token) {
                        $fcmService->sendToToken($token, $title, $body, $data);
                    }
                } else {
                    $fcmService->sendToToken($customerTokens, $title, $body, $data);
                }
            }
            // Gửi cho shipper
            $driverTokens = $order->driver->fcm_token;
            if ($driverTokens) {
                if (is_array($driverTokens)) {
                    foreach ($driverTokens as $token) {
                        $fcmService->sendToToken($token, $title, $body, $data);
                    }
                } else {
                    $fcmService->sendToToken($driverTokens, $title, $body, $data);
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => $proof
        ]);
    }
}
