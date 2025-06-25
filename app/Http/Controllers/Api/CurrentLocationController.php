<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CurrentLocationController extends Controller
{
    /**
     * @OA\Post(
     *      path="/driver/current-location",
     *      operationId="updateLocation",
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
    public function updateLocation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lat' => 'required|numeric',
            'lon' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response([
                'error' => true,
                'message' => $validator->messages()
            ], 422);
        }

        auth('driver')->user()->update([
            'current_location' => ['lat' => $request['lat'], 'lon' => $request['lon']]
        ]);

        return response()->json([
            'data' => [
                'location' => ['lat' => $request['lat'], 'lon' => $request['lon']]
            ]
        ]);
    }
}
