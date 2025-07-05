<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\FcmV1Service;

class FcmController extends Controller
{
    protected $fcmService;
    
    public function __construct(FcmV1Service $fcmService)
    {
        $this->fcmService = $fcmService;
    }

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
    
    /**
     * Test gửi notification đến FCM token
     */
    public function testSendToToken(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'fcm_token' => 'required|string',
                'title' => 'required|string|max:255',
                'body' => 'required|string|max:1000',
                'data' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => true,
                    'message' => $validator->messages()
                ], 422);
            }

            $result = $this->fcmService->sendToToken(
                $request->fcm_token,
                $request->title,
                $request->body,
                $request->data ?? []
            );

            return response()->json([
                'success' => $result,
                'message' => $result ? 'Notification sent successfully' : 'Failed to send notification'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Test gửi notification đến topic
     */
    public function testSendToTopic(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'topic' => 'required|string',
                'title' => 'required|string|max:255',
                'body' => 'required|string|max:1000',
                'data' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => true,
                    'message' => $validator->messages()
                ], 422);
            }

            $result = $this->fcmService->sendToTopic(
                $request->topic,
                $request->title,
                $request->body,
                $request->data ?? []
            );

            return response()->json([
                'success' => $result,
                'message' => $result ? 'Notification sent to topic successfully' : 'Failed to send notification to topic'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Subscribe token vào topic
     */
    public function subscribeToTopic(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'fcm_token' => 'required|string',
                'topic' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => true,
                    'message' => $validator->messages()
                ], 422);
            }

            $result = $this->fcmService->subscribeToTopic(
                $request->fcm_token,
                $request->topic
            );

            return response()->json([
                'success' => $result,
                'message' => $result ? 'Subscribed to topic successfully' : 'Failed to subscribe to topic'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Unsubscribe token khỏi topic
     */
    public function unsubscribeFromTopic(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'fcm_token' => 'required|string',
                'topic' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => true,
                    'message' => $validator->messages()
                ], 422);
            }

            $result = $this->fcmService->unsubscribeFromTopic(
                $request->fcm_token,
                $request->topic
            );

            return response()->json([
                'success' => $result,
                'message' => $result ? 'Unsubscribed from topic successfully' : 'Failed to unsubscribe from topic'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Validate FCM token
     */
    public function validateToken(Request $request)
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

            $result = $this->fcmService->validateToken($request->fcm_token);

            return response()->json([
                'valid' => $result,
                'message' => $result ? 'Token is valid' : 'Token is invalid'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
