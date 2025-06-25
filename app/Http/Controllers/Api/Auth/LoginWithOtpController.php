<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Utils\TwilioClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LoginWithOtpController extends Controller
{
    /**
     * @OA\Post(
     *      path="/login",
     *      operationId="login",
     *      tags={"Authentication"},
     *      summary="",
     *      description="",
     *      @OA\Response(response=200,description="successful operation", @OA\JsonContent()),
     *      @OA\Response(response=422, description="Bad request"),
     *      @OA\Response(response=500, description="Server error"),
     *     )
     */
    public function login(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'phone_number' => 'bail|required|regex:/^\+?[1-9]\d{1,14}$/|exists:users,phone_number',
                'otp' => 'bail|required_without:password|string',
                'password' => 'bail|required_without:otp|string',
            ], [
                'required' => config('errors.code.validation')['required'],
                'regex' => config('errors.code.validation')['regex'],
                'exists' => config('errors.code.validation')['exists'],
                'string' => config('errors.code.validation')['string'],
                'required_without' => config('errors.code.validation')['required_without'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => true,
                    'errorCode' => $validator->messages(),
                ], 422);
            }

            $user  = User::where('phone_number', $request['phone_number'])
                ->first();

            if ($request['otp'] != Cache::get('otp_user_' . $user->id) && empty($request['password'])) {
                return response()->json([
                    'error' => true,
                    'errorCode' => [
                        'otp' => config('errors.code.validation')['expired']
                    ],
                ], 422);
            }

            if (isset($request['password']) && !Hash::check($request['password'], $user->password)) {
                return response()->json([
                    'error' => true,
                    'errorCode' => [
                        'password' => config('errors.code.validation')['password']
                    ],
                ], 422);
            }

            Cache::forget('otp_user_' . $user->id);

            return response()->json([
                'data' => $user->createToken('loginToken')
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
     *      path="/login/otp",
     *      operationId="sendOtplogin",
     *      tags={"Authentication"},
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
                'phone_number' => 'bail|required|regex:/^\+?[1-9]\d{1,14}$/|exists:users,phone_number|'
            ], [
                'required' => config('errors.code.validation')['required'],
                'regex' => config('errors.code.validation')['regex'],
                'exists' => config('errors.code.validation')['exists'],
            ]);

            if ($validator->fails()) {
                return response([
                    'error' => true,
                    'errorCode' => $validator->messages()
                ], 422);
            }

            $otp = rand(1000, 9999);

            $message = "OTP: " . $otp;

            $userId  = User::where('phone_number', $request['phone_number'])
                ->value('id');

            Cache::put('otp_user_' . $userId, $otp, now()->addMinutes(5));

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
