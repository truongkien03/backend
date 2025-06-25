<?php

namespace App\Http\Controllers\Api\Driver\Auth;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Utils\TwilioClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RegisterController extends Controller
{
    /**
     * @OA\Post(
     *      path="/driver/register",
     *      operationId="registerdriver",
     *      tags={"driver"},
     *      summary="",
     *      description="",
     *      @OA\Response(response=200,description="successful operation", @OA\JsonContent()),
     *      @OA\Response(response=422, description="Bad request"),
     *      @OA\Response(response=500, description="Server error"),
     *     )
     */
    public function register(Request $request)
    {
        DB::beginTransaction();
        try {
            $validation = Validator::make($request->all(), [
                'phone_number' => 'bail|required|regex:/^\+?[1-9]\d{1,14}$/|unique:drivers,phone_number',
                'otp' => 'bail|required|string'
            ], [
                'regex' => config('errors.code.validation')['regex'],
                'unique' => config('errors.code.validation')['unique'],
                'string' => config('errors.code.validation')['string'],
                'required' => config('errors.code.validation')['required'],
            ]);

            if ($validation->fails()) {
                return response()->json([
                    'error' => true,
                    'errorCode' => $validation->messages()
                ], 422);
            }

            if ($request['otp'] != Cache::get('otp_register_driver_phone_number_' . $request['phone_number'])) {
                return response()->json([
                    'error' => true,
                    'errorCode' => [
                        'otp' => config('errors.code.validation')['expired']
                    ],
                ], 422);
            }

            $driver = Driver::create($request->only(['phone_number']));

            Cache::forget('otp_register_driver_phone_number_' . $request['phone_number']);

            DB::commit();
            return response()->json([
                'data' => $driver->createToken('loginToken')
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
     *      path="/driver/register/otp",
     *      operationId="sendOtpForRegister",
     *      tags={"driver"},
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
                'phone_number' => 'bail|required|regex:/^\+?[1-9]\d{1,14}$/|unique:drivers,phone_number'
            ], [
                'regex' => config('errors.code.validation')['regex'],
                'unique' => config('errors.code.validation')['unique'],
                'required' => config('errors.code.validation')['required'],
            ]);

            if ($validator->fails()) {
                return response([
                    'clientError' => true,
                    'errorCode' => $validator->messages()
                ], 422);
            }

            $otp = rand(1000, 9999);

            $message = "OTP: " . $otp;

            Cache::put('otp_register_driver_phone_number_' . $request['phone_number'], $otp, now()->addMinutes(5));

            TwilioClient::getClient()->messages->create(
                $request['phone_number'],
                [
                    'from' => config("twilio.number"),
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
}
