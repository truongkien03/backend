<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class LoginWithPasswordController extends Controller
{
    public function __invoke(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'phone_number' => 'required|exists:users,phone_number|regex:/^\+?[1-9]\d{1,14}$/',
                'password' => 'required|string|min:6'
            ], [
                'required' => config('errors.code.validation')['required'],
                'exists' => config('errors.code.validation')['exists'],
                'regex' => config('errors.code.validation')['regex'],
                'string' => config('errors.code.validation')['string'],
                'min' => config('errors.code.validation')['min'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => true,
                    'errorCode' => $validator->messages()
                ], 422);
            }

            $user = User::where('phone_number', $request->phone_number)->first();

            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'error' => true,
                    'errorCode' => [
                        'password' => [config('errors.code.validation')['password_mismatch']]
                    ]
                ], 422);
            }

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
} 