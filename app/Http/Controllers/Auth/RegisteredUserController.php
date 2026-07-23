<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterUserRequest;
use App\Models\User;
use App\Services\Security\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class RegisteredUserController extends Controller
{
    /**
     * Register a new account and sign the user in for web requests.
     */
    public function store(RegisterUserRequest $request, ActivityLogger $activityLogger): JsonResponse|RedirectResponse
    {
        $payload = $request->validated();

        $user = User::query()->create([
            'name' => $payload['name'],
            'email' => $payload['email'],
            'password' => $payload['password'],
            'company_name' => $payload['company_name'],
            'role' => $payload['role'] ?? User::ROLE_OWNER,
            'status' => User::STATUS_ACTIVE,
            'security_preferences' => [
                'login_alerts' => true,
                'two_factor_required' => false,
            ],
        ]);

        $activityLogger->record(
            module: 'auth',
            action: 'register',
            event: 'User account registered',
            description: 'Akun pengguna baru dibuat.',
            metadata: ['company_name' => $user->company_name],
            actor: $user,
        );

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'id' => $user->getKey(),
                    'name' => $user->name,
                    'email' => $user->email,
                    'company_name' => $user->company_name,
                    'role' => $user->role,
                    'status' => $user->status,
                ],
                'message' => 'Account registered successfully.',
            ], 201);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()
            ->route('dashboard')
            ->with('status', 'Akun berhasil dibuat.');
    }
}
