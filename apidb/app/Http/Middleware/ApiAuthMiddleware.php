<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;

class ApiAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->header('Authorization');

        if (!$token) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $token = str_replace('Bearer ', '', $token);

        $user = User::where('csrf_token', hash('sha256', $token))->first();

        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Invalid token'], 401);
        }

        $request->merge(['authUser' => $user]); // Attach user to request
        return $next($request);
    }
}
