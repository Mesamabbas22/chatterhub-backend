<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $userData = $validated;

        if (Schema::hasColumn('users', 'username')) {
            $userData['username'] = $this->uniqueUsername($validated['email']);
        }

        if (Schema::hasColumn('users', 'firstName')) {
            $userData['firstName'] = Str::before($validated['name'], ' ') ?: $validated['name'];
        }

        if (Schema::hasColumn('users', 'lastName')) {
            $userData['lastName'] = Str::after($validated['name'], ' ') ?: '';
        }

        $user = User::create($userData);

        $token = auth('api')->login($user);

        return $this->success('Registration successful', [
            'user' => $user,
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

        return $this->success('Login successful', [
            'user' => auth('api')->user(),
            'token' => $token,
        ]);
    }

    public function me()
    {
        return $this->success('Authenticated user fetched successfully', auth('api')->user());
    }

    public function logout()
    {
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
}
