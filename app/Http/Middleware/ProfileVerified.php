<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ProfileVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $driver = auth('driver')->user();

        if (!$driver->profileFilled()) {
            return response()->json([
                'error' => true,
                'errorCode' => 403,
            ], 403);
        }

        return $next($request);
    }
}
