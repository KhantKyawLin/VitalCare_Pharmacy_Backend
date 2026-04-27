<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPermission
{
    /**
     * Handle an incoming request.
     * Usage: ->middleware('permission:products.crud')
     * or ->middleware('permission:products.crud,orders.manage')
     */
    public function handle(Request $request, Closure $next, ...$permissions)
    {
        $user = auth('api')->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        // Superadmin and admin bypass permission checks
        if ($user->isAdmin()) {
            return $next($request);
        }

        // Check if user has any of the required permissions
        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }

        return response()->json(['error' => 'Forbidden. You do not have permission to perform this action.'], 403);
    }
}
