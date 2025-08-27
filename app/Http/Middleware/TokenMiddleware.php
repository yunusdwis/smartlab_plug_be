<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\User;

class TokenMiddleware
{
    public function handle($request, Closure $next)
    {
        $token = $request->bearerToken(); 
        $user = User::where('token', $token)->first();

        if (!$token || !$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->user = $user;

        return $next($request);
    }
}