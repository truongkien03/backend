<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SharingController extends Controller
{
    public function find(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'bail|required|regex:/^\+?[1-9]\d{1,14}$/|exists:drivers,phone_number|not_in:' . auth('driver')->user()->phone_number
        ], [
            'phone_number.exists' => 'Số điện thoại không tồn tại'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->messages()
            ], 422);
        }

        $driver = Driver::where('phone_number', $request['phone_number'])
            ->first();

        return $driver;
    }

    public function addToSharingGroup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'driver_id' => 'bail|required|exists:drivers,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->messages()
            ], 422);
        }

        $group = Group::firstOrCreate([
            'master_id' => auth('driver')->id(),
            'member_id' => $request['driver_id']
        ]);

        return $group;
    }

    public function sharingList(Request $request)
    {
        $drivers = auth('driver')->user()
            ->sharingGroup()
            ->where('status', config('const.driver.status.free'))
            ->get();

        return $drivers;
    }
}
