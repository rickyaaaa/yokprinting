<?php

namespace App\Http\Middleware;

use App\Models\Role;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return $this->deny($request, 'Unauthenticated.', 401);
        }

        if (! $user->isActive()) {
            return $this->deny($request, 'Your account is not active.', 403);
        }

        if ($user->role === User::ROLE_OWNER) {
            return $next($request);
        }

        $role = $user->roleDefinition()
            ->with('permissions')
            ->first();

        if (! $role || $role->status === Role::STATUS_DISABLED) {
            return $this->deny($request, 'This role is not allowed to access the resource.', 403);
        }

        $allowed = collect($permissions)
            ->every(fn (string $permission): bool => $role->permissions->contains('code', $permission));

        if (! $allowed) {
            return $this->deny($request, 'You do not have permission to access the resource.', 403);
        }

        return $next($request);
    }

    /**
     * Return the right denial response for API and web requests.
     */
    private function deny(Request $request, string $message, int $status): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => $message,
            ], $status);
        }

        abort($status, $message);
    }
}
