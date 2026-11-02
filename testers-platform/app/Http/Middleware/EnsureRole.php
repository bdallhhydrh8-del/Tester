<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || $user->status !== 'active') {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // "admin" means admin or super_admin, as in the SRS
        $allowed = collect($roles)->flatMap(
            fn ($role) => $role === 'admin' ? ['admin', 'super_admin'] : [$role]
        );

        if (! $allowed->contains($user->role->value)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $next($request);
    }
}