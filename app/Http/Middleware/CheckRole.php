<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $userRole = $user->role ? strtolower($user->role->name) : null;
        $requiredRoles = array_map('strtolower', $roles);

        if (!$userRole || !in_array($userRole, $requiredRoles)) {
            return response()->json([
                'message' => 'Access denied.',
                'your_role' => $user->role->name ?? 'No role',
                'required_roles' => $roles
            ], 403);
        }

        return $next($request);
    }
}
