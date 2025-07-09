<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OrderProofImage;
use Illuminate\Support\Facades\Validator;

class OrderProofImageController extends Controller
{

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,id',
            'image_url' => 'required|string',
            'note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->messages()
            ], 422);
        }

        $proof = OrderProofImage::create([
            'order_id' => $request->order_id,
            'shipper_id' => auth('driver')->id(),
            'image_url' => $request->image_url,
            'note' => $request->note,
        ]);

        return response()->json([
            'success' => true,
            'data' => $proof
        ]);
    }
}
