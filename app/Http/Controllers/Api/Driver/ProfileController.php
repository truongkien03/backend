<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\DriverProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    /**
     * @OA\Get(
     *      path="/driver/profile",
     *      operationId="driverprofile",
     *      tags={"driver"},
     *      summary="",
     *      description="",
     *      @OA\Response(response=200,description="successful operation", @OA\JsonContent()),
     *      @OA\Response(response=422, description="Bad request"),
     *      @OA\Response(response=500, description="Server error"),
     *      security={
     *          {"bearerAuth": {}}
     *      }
     *     )
     */
    public function profile(Request $request)
    {
        return response()->json([
            'data' => $request->user('driver')->load('profile')
        ]);
    }

    /**
     * @OA\Get(
     *      path="/driver/notifications",
     *      operationId="notifications",
     *      tags={"driver"},
     *      summary="",
     *      description="",
     *      @OA\Response(response=200,description="successful operation", @OA\JsonContent()),
     *      @OA\Response(response=422, description="Bad request"),
     *      @OA\Response(response=500, description="Server error"),
     *      security={
     *          {"bearerAuth": {}}
     *      }
     *     )
     */
    public function notifications(Request $request)
    {
        return response()->json([
            'data' => $request->user('driver')->notifications
        ]);
    }

    /**
     * @OA\Post(
     *      path="/driver/profile",
     *      operationId="driverupdateProfile",
     *      tags={"driver"},
     *      summary="",
     *      description="",
     *      @OA\Response(response=200,description="successful operation", @OA\JsonContent()),
     *      @OA\Response(response=422, description="Bad request"),
     *      @OA\Response(response=500, description="Server error"),
     *      security={
     *          {"bearerAuth": {}}
     *      }
     *     )
     */
    public function updateProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'gplx_front_url' => 'bail|required|url',
            'gplx_back_url' => 'bail|required|url',
            'baohiem_url' => 'bail|required|url',
            'dangky_xe_url' => 'bail|required|url',
            'cmnd_front_url' => 'bail|required|url',
            'cmnd_back_url' => 'bail|required|url',
            'reference_code' => 'bail|nullable|string|max:255',
            'name' => 'bail|required|string|max:50'
        ]);

        if ($validator->fails()) {
            return response([
                'error' => true,
                'message' => $validator->messages()
            ], 422);
        }

        auth()->user()->update([
            'name' => $request['name']
        ]);

        $profile = DriverProfile::updateOrCreate([
            'driver_id' => auth('driver')->id()
        ], $request->only([
            'gplx_front_url',
            'gplx_back_url',
            'baohiem_url',
            'dangky_xe_url',
            'cmnd_front_url',
            'cmnd_back_url',
            'reference_code',
        ]));

        return response()->json([
            'data' => $profile
        ]);
    }

    public function setStatusOnline()
    {
        $driver = auth('driver')->user();

        if ($driver->delivering_order_id) {
            $status = config('const.driver.status.busy');
        } else {
            $status = config('const.driver.status.free');
        }

        $driver->update([
            'status' => $status
        ]);

        return response()->json([
            'data' => auth('driver')->user()
        ]);
    }

    public function setStatusOffline()
    {
        auth('driver')->user()->update([
            'status' => config('const.driver.status.offline')
        ]);

        return response()->json([
            'data' => auth('driver')->user()
        ]);
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

    public function addFcmToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->messages()
            ], 422);
        }

        auth()->user()->update([
            'fcm_token' => $request['fcm_token']
        ]);

        $messaging = app('firebase.messaging');

        $messaging->subscribeToTopic(
            auth()->user()->routeNotificationForFcm(),
            $request['fcm_token']
        );

        return auth()->user();
    }

    public function removeFcmToken(Request $request)
    {
        $messaging = app('firebase.messaging');

        $messaging->unsubscribeFromTopic(
            auth()->user()->routeNotificationForFcm(),
            auth()->user()->fcm_token
        );

        auth()->user()->update([
            'fcm_token' => null
        ]);

        return auth()->user();
    }
}
