<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\User;

class TokenMiddleware
{
    public function handle($request, Closure $next)
    {
        $token = $request->bearerToken(); 

        $url = "https://v3.boothlab.id/api/check_token/$token";
        $opts = [
            "http" => [
                "method"  => "GET",
                "header"  => "Authorization: Bearer $token\r\n" .
                            "Accept: application/json\r\n"
            ]
        ];

        $context = stream_context_create($opts);
        $result  = @file_get_contents($url, false, $context);

        $statusLine = $http_response_header[0] ?? "HTTP/1.1 500 Internal Server Error";
        preg_match('{HTTP/\S+ (\d{3})}', $statusLine, $match);
        $statusCode = $match[1] ?? 500;

        if ($statusCode == 200){
            return $next($request);
        } 

        $user = User::where('token', $token)->first();

        if (!$token || !$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->user = $user;

        return $next($request);
    }
}