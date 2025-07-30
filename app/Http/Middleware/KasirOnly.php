<?php

    namespace App\Http\Middleware;

    use Closure;
    use Illuminate\Http\Request;
    use Symfony\Component\HttpFoundation\Response;

    class KasirOnly
    {
        public function handle(Request $request, Closure $next): Response
        {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // Fix: Gunakan relasi role
            if (!$user->role || strtolower($user->role->name) !== 'kasir') {
                return response()->json([
                    'message' => 'Access denied. Kasir only.',
                    'your_role' => $user->role->name ?? 'No role'
                ], 403);
            }

            return $next($request);
        }
    }
