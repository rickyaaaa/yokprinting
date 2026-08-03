<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginUserRequest;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Security\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    /**
     * Authenticate a user through API JSON or web session.
     *
     * @throws ValidationException
     */
    public function store(LoginUserRequest $request, ActivityLogger $activityLogger): JsonResponse|RedirectResponse
    {
        $credentials = $request->only('username', 'password');
        $remember = $request->boolean('remember');

        if ($request->expectsJson()) {
            try {
                $user = $this->validateApiCredentials($credentials['username'], $credentials['password']);
            } catch (ValidationException $exception) {
                $this->recordFailedLogin($activityLogger, $credentials['username']);

                throw $exception;
            }

            $this->recordSuccessfulLogin($user, $request->ip());
            $this->recordLoginActivity($activityLogger, $user, $remember);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'id' => $user->getKey(),
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'company_name' => $user->company_name,
                    'role' => $user->role,
                    'status' => $user->status,
                ],
                'auth' => [
                    'type' => 'session',
                    'remember' => $remember,
                ],
                'message' => 'Logged in successfully.',
            ]);
        }

        if (! Auth::attempt($credentials, $remember)) {
            $this->recordFailedLogin($activityLogger, $credentials['username']);

            throw ValidationException::withMessages([
                'username' => __('auth.failed'),
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->isActive()) {
            Auth::logout();
            $activityLogger->record(
                module: 'auth',
                action: 'login_blocked',
                event: 'Inactive user attempted login',
                description: 'Login ditolak karena akun tidak aktif.',
                metadata: ['username' => $credentials['username']],
                riskLevel: ActivityLog::RISK_MEDIUM,
                actor: $user,
            );

            throw ValidationException::withMessages([
                'username' => 'Akun ini belum aktif atau sedang dinonaktifkan.',
            ]);
        }

        $this->recordSuccessfulLogin($user, $request->ip());
        $this->recordLoginActivity($activityLogger, $user, $remember);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Destroy the current authenticated session.
     */
    public function destroy(Request $request, ActivityLogger $activityLogger): JsonResponse|RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user) {
            $activityLogger->record(
                module: 'auth',
                action: 'logout',
                event: 'User logged out',
                description: 'Pengguna keluar dari aplikasi.',
                actor: $user,
            );
        }

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Logged out successfully.',
            ]);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('status', 'Anda sudah keluar.');
    }

    /**
     * Resolve and validate API credentials without requiring API session middleware.
     *
     * @throws ValidationException
     */
    private function validateApiCredentials(string $username, string $password): User
    {
        $user = User::query()->where('username', $username)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'username' => __('auth.failed'),
            ]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'username' => 'Akun ini belum aktif atau sedang dinonaktifkan.',
            ]);
        }

        return $user;
    }

    /**
     * Persist last login metadata for audit and security UI.
     */
    private function recordSuccessfulLogin(User $user, ?string $ipAddress): void
    {
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $ipAddress,
        ])->save();
    }

    /**
     * Record a successful login activity.
     */
    private function recordLoginActivity(ActivityLogger $activityLogger, User $user, bool $remember): void
    {
        $activityLogger->record(
            module: 'auth',
            action: 'login',
            event: 'User logged in',
            description: 'Login berhasil.',
            metadata: ['remember' => $remember],
            actor: $user,
        );
    }

    /**
     * Record a failed login attempt without linking a user.
     */
    private function recordFailedLogin(ActivityLogger $activityLogger, string $username): void
    {
        $activityLogger->record(
            module: 'auth',
            action: 'login_failed',
            event: 'Failed login attempt',
            description: 'Percobaan login gagal.',
            metadata: ['username' => $username],
            riskLevel: ActivityLog::RISK_HIGH,
        );
    }
}
