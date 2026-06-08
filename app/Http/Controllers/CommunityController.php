<?php

namespace App\Http\Controllers;

use App\Models\Community;
use Illuminate\Http\Request;

class CommunityController extends Controller
{
    public function index()
    {
        return $this->success('Communities fetched successfully', Community::with('creator:id,name,email')->latest()->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:255'],
        ]);

        $community = Community::create([
            ...$validated,
            'created_by' => auth('api')->id(),
        ]);

        $community->users()->attach(auth('api')->id(), ['role' => 'admin']);

        return $this->success('Community created successfully', $community->load('creator:id,name,email', 'members.user:id,name,email'), 201);
    }

    public function show(Community $community)
    {
        return $this->success('Community fetched successfully', $community->load([
            'creator:id,name,email',
            'members.user:id,name,email',
            'conversations:id,community_id,type,name,last_message_at,created_at',
        ]));
    }

    public function join(Community $community)
    {
        $community->users()->syncWithoutDetaching([
            auth('api')->id() => ['role' => 'member'],
        ]);

        $community->conversations()->each(function ($conversation) {
            $conversation->users()->syncWithoutDetaching([
                auth('api')->id() => ['role' => 'member'],
            ]);
        });

        return $this->success('Joined community successfully', $community->load('members.user:id,name,email'));
    }

    public function leave(Community $community)
    {
        $community->users()->detach(auth('api')->id());

        $community->conversations()->each(function ($conversation) {
            $conversation->users()->detach(auth('api')->id());
        });

        return $this->success('Left community successfully');
    }
}
