<?php

// app/Http/Middleware/RoleMiddleware.php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        if (! $user || ! in_array($user->role, $roles)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        return $next($request);
    }
}

