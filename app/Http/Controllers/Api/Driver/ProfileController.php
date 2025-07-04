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
            'gplx_front' => 'bail|required|image|mimes:jpeg,png,jpg|max:2048',
            'gplx_back' => 'bail|required|image|mimes:jpeg,png,jpg|max:2048',
            'baohiem' => 'bail|required|image|mimes:jpeg,png,jpg|max:2048',
            'dangky_xe' => 'bail|required|image|mimes:jpeg,png,jpg|max:2048',
            'cmnd_front' => 'bail|required|image|mimes:jpeg,png,jpg|max:2048',
            'cmnd_back' => 'bail|required|image|mimes:jpeg,png,jpg|max:2048',
            'reference_code' => 'bail|nullable|string|max:255',
            'name' => 'bail|required|string|max:50',
            'email' => 'bail|nullable|email|max:255|unique:drivers,email,' . auth('driver')->id(),
        ]);

        if ($validator->fails()) {
            return response([
                'error' => true,
                'message' => $validator->messages()
            ], 422);
        }

        $driver = auth('driver')->user();
        $driverId = $driver->id;
        
        // Upload và lưu các ảnh vào local storage
        $imageFields = [
            'gplx_front' => 'gplx_front_url',
            'gplx_back' => 'gplx_back_url', 
            'baohiem' => 'baohiem_url',
            'dangky_xe' => 'dangky_xe_url',
            'cmnd_front' => 'cmnd_front_url',
            'cmnd_back' => 'cmnd_back_url'
        ];

        $uploadedUrls = [];

        foreach ($imageFields as $inputName => $dbField) {
            if ($request->hasFile($inputName)) {
                // Tạo tên file unique
                $file = $request->file($inputName);
                $filename = $driverId . '_' . $inputName . '_' . time() . '.' . $file->getClientOriginalExtension();
                
                // Lưu vào thư mục public/storage/driver_documents
                $path = $file->storeAs('driver_documents', $filename, 'public');
                
                // Tạo URL public để access
                $uploadedUrls[$dbField] = asset('storage/' . $path);
            }
        }

        // Cập nhật thông tin driver
        $updateData = ['name' => $request->name];
        
        if ($request->has('email') && $request->email !== null) {
            $updateData['email'] = $request->email;
        }
        
        $driver->update($updateData);

        // Lấy profile hiện tại để giữ lại ảnh cũ nếu không upload ảnh mới
        $existingProfile = DriverProfile::where('driver_id', $driverId)->first();
        
        $profileData = [
            'reference_code' => $request->reference_code,
        ];
        
        // Merge uploaded URLs với dữ liệu hiện có
        foreach ($imageFields as $inputName => $dbField) {
            if (isset($uploadedUrls[$dbField])) {
                // Xóa ảnh cũ nếu có
                if ($existingProfile && $existingProfile->{$dbField}) {
                    $oldPath = str_replace(asset('storage/'), '', $existingProfile->{$dbField});
                    Storage::disk('public')->delete($oldPath);
                }
                $profileData[$dbField] = $uploadedUrls[$dbField];
            } elseif ($existingProfile) {
                // Giữ lại ảnh cũ nếu không upload ảnh mới
                $profileData[$dbField] = $existingProfile->{$dbField};
            }
        }

        $profile = DriverProfile::updateOrCreate([
            'driver_id' => $driverId
        ], $profileData);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'driver' => $driver->fresh()->load('profile'),
                'profile' => $profile
            ]
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
