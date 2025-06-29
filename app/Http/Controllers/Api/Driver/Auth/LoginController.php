<?php

namespace App\Http\Controllers\Api\Driver\Auth;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Utils\TwilioClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class LoginController extends Controller
{
    /**
     * @OA\Post(
     *      path="/driver/login",
     *      operationId="loginWithOtp",
     *      tags={"driver"},
     *      summary="",
     *      description="",
     *      @OA\Response(response=200,description="successful operation", @OA\JsonContent()),
     *      @OA\Response(response=422, description="Bad request"),
     *      @OA\Response(response=500, description="Server error"),
     *     )
     */
    public function loginWithOtp(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'phone_number' => 'bail|required|exists:drivers,phone_number|regex:/^\+?[1-9]\d{1,14}$/',
                'otp' => 'bail|required|string'
            ], [
                'required' => config('errors.code.validation')['required'],
                'exists' => config('errors.code.validation')['exists'],
                'regex' => config('errors.code.validation')['regex'],
                'string' => config('errors.code.validation')['string'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => true,
                    'errorCode' => $validator->messages()
                ], 422);
            }

            $driver  = Driver::where('phone_number', $request['phone_number'])
                ->first();

            if ($request['otp'] != Cache::get('otp_driver_' . $driver->id)) {
                return response()->json([
                    'error' => true,
                    'errorCode' => [
                        'otp' => config('errors.code.validation')['expired']
                    ],
                ], 422);
            }

            Cache::forget('otp_driver_' . $driver->id);

            foreach($driver->tokens as $token) {
                $token->revoke();
            }

            return response()->json([
                'data' => $driver->createToken('loginToken')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => [
                    $e->getMessage()
                ],
                'errorCode' => 500
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *      path="/driver/login/otp",
     *      operationId="loginsendOtp",
     *      tags={"driver"},
     *      summary="",
     *      description="",
     *      @OA\Response(response=200,description="successful operation", @OA\JsonContent()),
     *      @OA\Response(response=422, description="Bad request"),
     *      @OA\Response(response=500, description="Server error"),
     *     )
     */
    public function sendOtp(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'phone_number' => 'bail|required|regex:/^\+?[1-9]\d{1,14}$/|exists:drivers,phone_number'
            ], [
                'exists' => config('errors.code.validation')['exists'],
                'regex' => config('errors.code.validation')['regex'],
                'required' => config('errors.code.validation')['required'],
            ]);

            if ($validator->fails()) {
                return response([
                    'error' => true,
                    'errorCode' => $validator->messages()
                ], 422);
            }

            $otp = rand(1000, 9999);

            $message = "OTP: " . $otp;

            $driverId  = Driver::where('phone_number', $request['phone_number'])
                ->value('id');

            Cache::put('otp_driver_' . $driverId, $otp, now()->addMinutes(5));

            TwilioClient::getClient()->messages->create(
                $request['phone_number'],
                [
                    'from' => config('twilio.number'),
                    'body' => $message
                ]
            );

            return response([], 204);
        } catch (\Exception $e) {
            return response([
                'error' => true,
                'message' => [$e->getMessage()],
                'errorCode' => 500
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *      path="/driver/login/password",
     *      operationId="loginWithPassword",
     *      tags={"driver"},
     *      summary="Login driver with phone number and password",
     *      description="Alternative login method using password instead of OTP",
     *      @OA\Parameter(
     *          name="phone_number",
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
     *              type="string"
     *          )
     *      ),
     *      @OA\Response(response=200,description="Login successful", @OA\JsonContent()),
     *      @OA\Response(response=422, description="Validation failed"),
     *      @OA\Response(response=401, description="Invalid credentials"),
     *      @OA\Response(response=500, description="Server error"),
     *     )
     */
    public function loginWithPassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'phone_number' => 'bail|required|regex:/^\+?[1-9]\d{1,14}$/|exists:drivers,phone_number',
                'password' => 'bail|required|string'
            ], [
                'phone_number.required' => 'Số điện thoại là bắt buộc',
                'phone_number.exists' => 'Số điện thoại không tồn tại',
                'phone_number.regex' => 'Định dạng số điện thoại không hợp lệ',
                'password.required' => 'Mật khẩu là bắt buộc',
                'password.string' => 'Mật khẩu phải là chuỗi ký tự',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => true,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $driver = Driver::where('phone_number', $request->phone_number)->first();

            // Kiểm tra tài xế có mật khẩu không
            if (!$driver->password) {
                return response()->json([
                    'error' => true,
                    'message' => 'Driver does not have a password. Please use OTP login or set a password first.'
                ], 401);
            }

            // Kiểm tra mật khẩu
            if (!Hash::check($request->password, $driver->password)) {
                return response()->json([
                    'error' => true,
                    'message' => 'Invalid phone number or password'
                ], 401);
            }

            // Revoke old tokens (single session)
            foreach($driver->tokens as $token) {
                $token->revoke();
            }

            // Generate new token
            $tokenData = $driver->createToken('loginToken');

            return response()->json([
                'message' => 'Login successful',
                'data' => $tokenData
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
