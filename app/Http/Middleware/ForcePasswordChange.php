<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('api')->user();

        if ($user && $user->must_change_password) {
            // Allow them to logout or change password
            if ($request->is('api/auth/logout') || $request->is('api/auth/force-change-password') || $request->is('api/auth/me')) {
                return $next($request);
            }

            return response()->json([
                'error' => 'ForcePasswordChangeRequired',
                'message' => 'You must change your password before proceeding.'
            ], 403);
        }

        return $next($request);
    }
}
