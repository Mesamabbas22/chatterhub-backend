<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\ConversationRequest;
use App\Models\User;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function index()
    {
        $conversations = auth('api')->user()
            ->conversations()
            ->with([
                'users:id,name,email,is_online,last_seen_at',
                'community:id,name',
                'messages' => fn ($query) => $query->latest()->limit(1)->with('sender:id,name,email,is_online,last_seen_at'),
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('conversations.created_at')
            ->get()
            ->filter(fn (Conversation $conversation) => $this->canAppearInConversationList($conversation))
            ->values();

        return $this->success('Conversations fetched successfully', $conversations);
    }

    public function startPrivateChat(Request $request)
    {
        $validated = $request->validate([
            'receiver_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $user = auth('api')->user();

        if ((int) $validated['receiver_id'] === (int) $user->id) {
            return $this->error('You cannot start a private chat with yourself', [], 422);
        }

        $receiver = User::findOrFail($validated['receiver_id']);

        $conversation = Conversation::query()
            ->where('type', 'private')
            ->whereHas('users', fn ($query) => $query->where('users.id', $user->id))
            ->whereHas('users', fn ($query) => $query->where('users.id', $receiver->id))
            ->withCount('participants')
            ->get()
            ->firstWhere('participants_count', 2);

        if (! $conversation) {
            $conversation = Conversation::create(['type' => 'private']);
            $conversation->users()->attach([
                $user->id => ['role' => 'member'],
                $receiver->id => ['role' => 'member'],
            ]);
        }

        return $this->success(
            'Private conversation ready',
            $conversation->load(['users:id,name,email,is_online,last_seen_at', 'participants'])
        );
    }

    public function show(Conversation $conversation)
    {
        if (! $this->isParticipant($conversation)) {
            return $this->error('You are not a participant of this conversation', [], 403);
        }

        return $this->success('Conversation fetched successfully', $conversation->load([
            'participants.user:id,name,email,is_online,last_seen_at',
            'messages.sender:id,name,email,is_online,last_seen_at',
            'community:id,name',
        ]));
    }

    private function isParticipant(Conversation $conversation): bool
    {
        return $conversation->participants()
            ->where('user_id', auth('api')->id())
            ->exists();
    }

    private function canAppearInConversationList(Conversation $conversation): bool
    {
        if ($conversation->type !== 'private') {
            return true;
        }

        $authUserId = auth('api')->id();
        $otherUser = $conversation->users->firstWhere('id', '!=', $authUserId);

        if (! $otherUser) {
            return false;
        }

        return ConversationRequest::query()
            ->where('status', 'approved')
            ->where(function ($query) use ($authUserId, $otherUser) {
                $query->where(function ($builder) use ($authUserId, $otherUser) {
                    $builder->where('sender_id', $authUserId)
                        ->where('receiver_id', $otherUser->id);
                })->orWhere(function ($builder) use ($authUserId, $otherUser) {
                    $builder->where('sender_id', $otherUser->id)
                        ->where('receiver_id', $authUserId);
                });
            })
            ->exists();
    }
}
