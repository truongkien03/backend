<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FcmController extends Controller
{
    /**
     * Thêm FCM token cho user
     */
    public function addFcmToken(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'fcm_token' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => true,
                    'message' => $validator->messages()
                ], 422);
            }

            $user = auth()->user();
            $fcmToken = $request->fcm_token;

            // Lấy danh sách FCM tokens hiện tại
            $fcmTokens = $user->fcm_token ?? [];

            // Đảm bảo fcm_tokens là array
            if (!is_array($fcmTokens)) {
                $fcmTokens = $fcmTokens ? [$fcmTokens] : [];
            }

            // Kiểm tra token đã tồn tại chưa
            if (!in_array($fcmToken, $fcmTokens)) {
                $fcmTokens[] = $fcmToken;
                $user->update(['fcm_token' => $fcmTokens]);
            }

            return response()->json([], 204);

        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => [$e->getMessage()],
                'errorCode' => 500
            ], 500);
        }
    }

    /**
     * Xóa FCM token cho user
     */
    public function removeFcmToken(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'fcm_token' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => true,
                    'message' => $validator->messages()
                ], 422);
            }

            $user = auth()->user();
            $fcmTokenToRemove = $request->fcm_token;

            // Lấy danh sách FCM tokens hiện tại
            $fcmTokens = $user->fcm_token ?? [];

            // Đảm bảo fcm_tokens là array
            if (!is_array($fcmTokens)) {
                $fcmTokens = $fcmTokens ? [$fcmTokens] : [];
            }

            // Xóa token khỏi danh sách
            $fcmTokens = array_filter($fcmTokens, function($token) use ($fcmTokenToRemove) {
                return $token !== $fcmTokenToRemove;
            });

            // Reset lại index của array
            $fcmTokens = array_values($fcmTokens);

            $user->update(['fcm_token' => $fcmTokens]);

            return response()->json([], 204);

        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => [$e->getMessage()],
                'errorCode' => 500
            ], 500);
        }
    }
}
