<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user || !$user->hasRoleOrHigher(min_role($roles))) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $next($request);
    }
}

// Helper to get the lowest required role from a list
function min_role(array $roles): string
{
    $hierarchy = ['customer' => 1, 'admin' => 2, 'super_admin' => 3];
    return collect($roles)->sortBy(fn($r) => $hierarchy[$r] ?? 0)->first();
}
