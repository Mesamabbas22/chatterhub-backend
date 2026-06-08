<?php

namespace App\Http\Controllers;

use App\Models\Community;
use Illuminate\Http\Request;

class CommunityConversationController extends Controller
{
    public function index(Community $community)
    {
        if (! $this->isMember($community)) {
            return $this->error('You are not a member of this community', [], 403);
        }

        return $this->success(
            'Community conversations fetched successfully',
            $community->conversations()->with('messages.sender:id,name,email')->latest()->get()
        );
    }

    public function store(Request $request, Community $community)
    {
        if (! $this->isMember($community)) {
            return $this->error('You are not a member of this community', [], 403);
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $conversation = $community->conversations()->create([
            'type' => 'community',
            'name' => $validated['name'] ?? null,
        ]);

        $participants = $community->members()
            ->pluck('user_id')
            ->mapWithKeys(fn ($userId) => [
                $userId => ['role' => (int) $userId === (int) auth('api')->id() ? 'admin' : 'member'],
            ])
            ->all();

        $conversation->users()->syncWithoutDetaching($participants);

        return $this->success('Community conversation created successfully', $conversation->load('community:id,name', 'users:id,name,email'), 201);
    }

    private function isMember(Community $community): bool
    {
        return $community->members()
            ->where('user_id', auth('api')->id())
            ->exists();
    }
}
