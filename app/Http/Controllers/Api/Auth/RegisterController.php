<?php

namespace App\Http\Controllers\Api\Auth;

use App\Models\User;
use App\Utils\TwilioClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RegisterController
{ /**
     * @OA\Post(
     *     path="/api/register/otp",
     *     summary="Gửi OTP cho đăng ký",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"phone_number"},
     *             @OA\Property(property="phone_number", type="string", example="+84987654321")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OTP sent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="OTP sent successfully"),
     *             @OA\Property(property="otp", type="string", example="123456")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function register(Request $request)
    {
        DB::beginTransaction();
        try {
            $validation = Validator::make($request->all(), [
                'phone_number' => 'bail|required|max:255|unique:users,phone_number|regex:/^\+?[1-9]\d{1,14}$/',
                'otp' => 'bail|required|string'
            ], [
                'required' => config('errors.code.validation')['required'],
                'max' => config('errors.code.validation')['max'],
                'regex' => config('errors.code.validation')['regex'],
                'unique' => config('errors.code.validation')['unique'],
                'string' => config('errors.code.validation')['string'],
            ]);

            if ($validation->fails()) {
                return response()->json([
                    'error' => true,
                    'errorCode' => $validation->messages()
                ], 422);
            }

            if ($request['otp'] != Cache::get('otp_register_phone_number_' . $request['phone_number'])) {
                return response()->json([
                    'error' => true,
                    'errorCode' => [
                        'otp' => config('errors.code.validation')['expired']
                    ],
                ], 422);
            }

            $user = User::create($request->only(['phone_number']));

            Cache::forget('otp_register_phone_number_' . $request['phone_number']);

            DB::commit();

            return response()->json([
                'data' => $user->createToken('loginToken')
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'error' => true,
                'messages' => [
                    $e->getMessage()
                ],
                'errorCode' => 500
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *      path="/register/otp",
     *      operationId="sendOtpForRegister",
     *      tags={"Authentication"},
     *      summary="",
     *      description="",
     *      @OA\Response(response=200,description="successful operation", @OA\JsonContent()),
     *      @OA\Response(response=422, description="Bad request"),
     *      @OA\Response(response=500, description="Server error"),
     *     )
     */
    public function sendOtpForRegister(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'phone_number' => 'bail|required|unique:users,phone_number|regex:/^\+?[1-9]\d{1,14}$/'
            ], [
                'required' => config('errors.code.validation')['required'],
                'unique' => config('errors.code.validation')['unique'],
                'regex' => config('errors.code.validation')['regex'],
            ]);

            if ($validator->fails()) {
                return response([
                    'error' => true,
                    'errorCode' => $validator->messages()
                ], 422);
            }

            $otp = rand(1000, 9999);

            $message = "OTP: " . $otp;

            Cache::put('otp_register_phone_number_' . $request['phone_number'], $otp, now()->addMinutes(5));

            TwilioClient::getClient()->messages->create(
                $request['phone_number'],
                [
                    'from' => config("twilio.number"),
                    'body' => $message
                ]
            );

            return response()->json([], 204);
        } catch (\Exception $e) {
            return response([
                'error' => true,
                'message' => [$e->getMessage()],
                'errorCode' => 500
            ], 500);
        }
    }
}
