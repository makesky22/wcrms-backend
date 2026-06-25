<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        if (!$request->user()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        if (!in_array($request->user()->role, $roles)) {
            return response()->json(['message' => 'Unauthorized. Insufficient permissions.'], 403);
        }
        return $next($request);
    }
}
