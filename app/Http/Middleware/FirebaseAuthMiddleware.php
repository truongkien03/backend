<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FirebaseAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        $expectedToken = config('firebase.api_token') ?? env('FIREBASE_API_TOKEN');
        
        if (!$token) {
            Log::warning('Firebase API call without token', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            
            return response()->json([
                'error' => true,
                'message' => 'Missing authorization token'
            ], 401);
        }
        
        if ($token !== $expectedToken) {
            Log::warning('Firebase API call with invalid token', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'provided_token' => substr($token, 0, 10) . '...'
            ]);
            
            return response()->json([
                'error' => true,
                'message' => 'Invalid authorization token'
            ], 401);
        }
        
        // Token hợp lệ
        Log::info('Firebase API call authenticated', [
            'ip' => $request->ip(),
            'endpoint' => $request->path()
        ]);
        
        return $next($request);
    }
} 