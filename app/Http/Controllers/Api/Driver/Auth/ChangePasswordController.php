<?php

namespace App\Http\Controllers\Api\Driver\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ChangePasswordController extends Controller
{
    /**
     * @OA\Post(
     *      path="/driver/change-password",
     *      operationId="driverChangePassword",
     *      tags={"driver"},
     *      summary="Change driver password",
     *      description="Allows driver to change their existing password",
     *      @OA\Parameter(
     *          name="current_password",
     *          required=true,
     *          in="query",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="password",
     *          required=true,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *              minimum=6
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="password_confirmation",
     *          required=true,
     *          in="query",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Response(response=200,description="Password changed successfully", @OA\JsonContent()),
     *      @OA\Response(response=422, description="Validation failed"),
     *      @OA\Response(response=400, description="Current password incorrect"),
     *      @OA\Response(response=500, description="Server error"),
     *      security={
     *          {"bearerAuth": {}}
     *      }
     *     )
     */
    public function __invoke(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'current_password' => 'required|string',
                'password' => 'required|string|min:6|confirmed',
            ], [
                'current_password.required' => 'Mật khẩu hiện tại là bắt buộc',
                'password.required' => 'Mật khẩu mới là bắt buộc',
                'password.string' => 'Mật khẩu phải là chuỗi ký tự',
                'password.min' => 'Mật khẩu phải có ít nhất 6 ký tự',
                'password.confirmed' => 'Xác nhận mật khẩu không khớp',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => true,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $driver = auth('driver')->user();
            
            // Kiểm tra nếu tài xế chưa có mật khẩu
            if (!$driver->password) {
                return response()->json([
                    'error' => true,
                    'message' => 'Driver does not have a password. Use set password API instead.'
                ], 400);
            }

            // Kiểm tra mật khẩu hiện tại
            if (!Hash::check($request->current_password, $driver->password)) {
                return response()->json([
                    'error' => true,
                    'message' => 'Current password is incorrect'
                ], 400);
            }

            // Cập nhật mật khẩu mới
            $driver->password = Hash::make($request->password);
            $driver->save();

            // Revoke all existing tokens for security
            foreach($driver->tokens as $token) {
                $token->revoke();
            }

            return response()->json([
                'message' => 'Password changed successfully. Please login again with your new password.',
                'data' => [
                    'tokens_revoked' => true
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'Server error occurred',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}
