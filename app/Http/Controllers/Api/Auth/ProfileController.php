<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Utils\TwilioClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    /**
     * @OA\Get(
     *      path="/profile",
     *      operationId="getProfile",
     *      tags={"Profile"},
     *      summary="",
     *      description="",
     *      @OA\Response(response=200,description="successful operation", @OA\JsonContent()),
     *      @OA\Response(response=422, description="Bad request"),
     *      @OA\Response(response=500, description="Server error"),
     *      *      security={
     *          {"bearerAuth": {}}
     *      }
     *     )
     */
    public function getProfile(Request $request)
    {
        return $request->user();
    }

    /**
     * @OA\Post(
     *      path="/profile",
     *      operationId="loginProfile",
     *      tags={"Profile"},
     *      summary="",
     *      description="",
     *      @OA\Response(response=200,description="successful operation", @OA\JsonContent()),
     *      @OA\Response(response=422, description="Bad request"),
     *      @OA\Response(response=500, description="Server error"),
     *      *      security={
     *          {"bearerAuth": {}}
     *      }
     *     )
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|max:255',
            'phone_number' => 'nullable|max:255',
            'address' => 'required',
            'address.lat' => 'required|numeric',
            'address.lon' => 'required|numeric',
            'address.desc' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response([
                'error' => true,
                'message' => $validator->messages()
            ], 422);
        }

        $data = [];

        if (isset($request['name'])) {
            $data['name'] = $request['name'];
        }

        if (isset($request['phone_number'])) {
            $data['phone_number'] = $request['phone_number'];
        }

        if (isset($request['address'])) {
            $data['address'] = $request['address'];
        }

        auth()->user()->update($data);

        return response()->json([
            'data' => auth()->user()
        ]);
    }

    public function changePassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'password' => 'bail|required|string|min:6|confirmed',
            ], [
                'required' => config('errors.code.validation')['required'],
                'string' => config('errors.code.validation')['string'],
                'min' => config('errors.code.validation')['min'],
                'confirmed' => config('errors.code.validation')['confirmed'],
            ]);

            if ($validator->fails()) {
                return response([
                    'error' => true,
                    'errorCode' => $validator->messages()
                ], 422);
            }

            $request['password'] = bcrypt($request['password']);

            auth()->user()->update([
                'password' => $request['password']
            ]);

            return response([], 204);
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

    public function resetPassword(Request $request)
    {
        try {
            $validation = Validator::make($request->all(), [
                'phone_number' => 'bail|required|max:255|regex:/^\+?[1-9]\d{1,14}$/|exists:users,phone_number',
                'otp' => 'bail|required|string',
                'password' => 'bail|required|string|min:6|confirmed',
            ], [
                'required' => config('errors.code.validation')['required'],
                'max' => config('errors.code.validation')['max'],
                'regex' => config('errors.code.validation')['regex'],
                'unique' => config('errors.code.validation')['unique'],
                'string' => config('errors.code.validation')['string'],
                'min' => config('errors.code.validation')['min'],
                'confirmed' => config('errors.code.validation')['confirmed'],
            ]);

            if ($validation->fails()) {
                return response()->json([
                    'error' => true,
                    'errorCode' => $validation->messages()
                ], 422);
            }

            if ($request['otp'] != Cache::get('otp_forgot_password_' . $request['phone_number'])) {
                return response()->json([
                    'error' => true,
                    'errorCode' => [
                        'otp' => config('errors.code.validation')['expired']
                    ],
                ], 422);
            }

            $user = User::where('phone_number', $request->only(['phone_number']))->first();

            $user->update([
                'password' => bcrypt($request['password'])
            ]);

            Cache::forget('otp_forgot_password_' . $request['phone_number']);

            return response()->json([
                'data' => $user->createToken('loginToken')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'messages' => [
                    $e->getMessage()
                ],
                'errorCode' => 500
            ], 500);
        }
    }

    public function forgotPassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'phone_number' => 'bail|required|exists:users,phone_number|regex:/^\+?[1-9]\d{1,14}$/'
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

            Cache::put('otp_forgot_password_' . $request['phone_number'], $otp, now()->addMinutes(5));

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

    public function changeAvatar(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'avatar' => 'required|url|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => true,
                    'message' => $validator->messages()
                ], 422);
            }

            auth()->user()->update([
                'avatar' => $request['avatar']
            ]);

            return response()->json([
                'avatar' => $request['avatar']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => [
                    $e->getMessage()
                ]
            ]);
        }
    }

    public function notifications(Request $request)
    {
        return response()->json([
            'data' => $request->user()->notifications()->latest()->paginate()
        ]);
    }
}
