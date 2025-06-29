<?php

namespace App\Http\Controllers\Api\Driver\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class SetPasswordController extends Controller
{
    /**
     * @OA\Post(
     *      path="/driver/set-password",
     *      operationId="driverSetPassword",
     *      tags={"driver"},
     *      summary="Set initial password for driver",
     *      description="Allows driver to set their first password. Can only be used if driver doesn't have a password yet.",
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
     *      @OA\Response(response=200,description="Password set successfully", @OA\JsonContent()),
     *      @OA\Response(response=422, description="Validation failed"),
     *      @OA\Response(response=400, description="Driver already has password"),
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
                'password' => 'required|string|min:6|confirmed',
            ], [
                'required' => 'Mật khẩu là bắt buộc',
                'string' => 'Mật khẩu phải là chuỗi ký tự',
                'min' => 'Mật khẩu phải có ít nhất 6 ký tự',
                'confirmed' => 'Xác nhận mật khẩu không khớp',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => true,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $driver = auth('driver')->user();
            
            // Kiểm tra nếu tài xế đã có mật khẩu
            if ($driver->password) {
                return response()->json([
                    'error' => true,
                    'message' => 'Driver already has a password. Use change password API instead.'
                ], 400);
            }

            // Set mật khẩu lần đầu
            $driver->password = Hash::make($request->password);
            $driver->save();

            return response()->json([
                'message' => 'Password set successfully',
                'data' => [
                    'has_password' => true
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
