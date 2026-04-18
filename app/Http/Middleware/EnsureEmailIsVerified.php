<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()?->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Your email address is not verified.',
                'action'  => 'verify_email',
            ], 403);
        }
        return $next($request);
    }
}
