<?php

namespace App\Http\Controllers;

use App\Events\UserStatusUpdated;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'firstName' => ['required', 'string', 'max:255'],
            'lastName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $userData = $validated;

        if (Schema::hasColumn('users', 'username')) {
            $userData['username'] = $this->uniqueUsername($validated['email']);
        }

        if (Schema::hasColumn('users', 'firstName')) {
            $userData['firstName'] = $validated['firstName'];
        }

        if (Schema::hasColumn('users', 'lastName')) {
            $userData['lastName'] = $validated['lastName'];
        }

        $user = User::create($userData);

        $token = auth('api')->login($user);
        $this->markOnline($user);

        return $this->success('Registration successful', [
            'user' => $user->fresh(),
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!$token = auth('api')->attempt($credentials)) {
            return $this->error('Invalid email or password', [], 401);
        }

        $user = auth('api')->user();
        $this->markOnline($user);

        return $this->success('Login successful', [
            'user' => $user->fresh(),
            'token' => $token,
        ]);
    }

    public function me()
    {
        return $this->success('Authenticated user fetched successfully', auth('api')->user());
    }

    public function logout()
    {
        $user = auth('api')->user();

        $user->forceFill([
            'is_online' => false,
            'last_seen_at' => now(),
        ])->save();

        broadcast(new UserStatusUpdated($user->fresh()));

        auth('api')->logout();

        return $this->success('Logout successful');
    }

    private function uniqueUsername(string $email): string
    {
        $base = Str::slug(Str::before($email, '@')) ?: 'user';
        $username = $base;

        while (User::where('username', $username)->exists()) {
            $username = $base.'-'.Str::lower(Str::random(6));
        }

        return $username;
    }

    private function markOnline(User $user): void
    {
        $user->forceFill([
            'is_online' => true,
        ])->save();

        broadcast(new UserStatusUpdated($user->fresh()));
    }
}
