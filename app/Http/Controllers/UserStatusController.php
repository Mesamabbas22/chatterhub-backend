<?php

namespace App\Http\Controllers;

use App\Events\UserStatusUpdated;
use App\Models\User;

class UserStatusController extends Controller
{
    public function online()
    {
        $user = auth('api')->user();

        $user->forceFill([
            'is_online' => true,
        ])->save();

        broadcast(new UserStatusUpdated($user->fresh()));

        return $this->success('User marked online', $this->statusPayload($user->fresh()));
    }

    public function offline()
    {
        $user = auth('api')->user();

        $user->forceFill([
            'is_online' => false,
            'last_seen_at' => now(),
        ])->save();

        broadcast(new UserStatusUpdated($user->fresh()));

        return $this->success('User marked offline', $this->statusPayload($user->fresh()));
    }

    public function show(User $user)
    {
        return $this->success('User status fetched successfully', $this->statusPayload($user));
    }

    /**
     * @return array<string, mixed>
     */
    private function statusPayload(User $user): array
    {
        return [
            'user_id' => $user->id,
            'is_online' => $user->is_online,
            'last_seen_at' => $user->last_seen_at,
        ];
    }
}
