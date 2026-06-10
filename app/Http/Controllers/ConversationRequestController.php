<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\ConversationRequest;
use App\Models\User;
use Illuminate\Http\Request;

class ConversationRequestController extends Controller
{
    public function searchUsers(Request $request)
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'max:255'],
        ]);

        $authUserId = auth('api')->id();
        $query = $validated['query'];

        $users = User::query()
            ->select('id', 'name', 'email', 'is_online', 'last_seen_at')
            ->where('id', '!=', $authUserId)
            ->where(function ($builder) use ($query) {
                $builder->where('name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");
            })
            ->orderBy('name')
            ->get()
            ->map(function (User $user) use ($authUserId) {
                $request = $this->findRequestBetween($authUserId, $user->id);

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_online' => $user->is_online,
                    'last_seen_at' => $user->last_seen_at,
                    'request_status' => $request?->status,
                ];
            });

        return $this->success('Users found', $users);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'receiver_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $senderId = auth('api')->id();
        $receiverId = (int) $validated['receiver_id'];

        if ($senderId === $receiverId) {
            return $this->error('You cannot send a conversation request to yourself', [], 422);
        }

        $existingRequest = $this->findActiveRequestBetween($senderId, $receiverId);

        if ($existingRequest?->status === 'pending') {
            return $this->success('Request already pending', $existingRequest->load('sender:id,name,email,is_online,last_seen_at', 'receiver:id,name,email,is_online,last_seen_at'));
        }

        if ($existingRequest?->status === 'approved') {
            return $this->success('Request already approved', [
                'conversation' => $this->createOrGetPrivateConversation($senderId, $receiverId),
            ]);
        }

        $sameDirectionRequest = ConversationRequest::query()
            ->where('sender_id', $senderId)
            ->where('receiver_id', $receiverId)
            ->first();

        if ($sameDirectionRequest) {
            $sameDirectionRequest->update(['status' => 'pending']);
            $conversationRequest = $sameDirectionRequest;
        } else {
            $conversationRequest = ConversationRequest::create([
                'sender_id' => $senderId,
                'receiver_id' => $receiverId,
                'status' => 'pending',
            ]);
        }

        return $this->success(
            'Conversation request sent successfully',
            $conversationRequest->load('sender:id,name,email,is_online,last_seen_at', 'receiver:id,name,email,is_online,last_seen_at'),
            201
        );
    }

    public function received()
    {
        $requests = ConversationRequest::query()
            ->with('sender:id,name,email,is_online,last_seen_at')
            ->where('receiver_id', auth('api')->id())
            ->where('status', 'pending')
            ->latest()
            ->get();

        return $this->success('Received conversation requests fetched successfully', $requests);
    }

    public function approve(int $request)
    {
        $conversationRequest = ConversationRequest::findOrFail($request);

        if ((int) $conversationRequest->receiver_id !== (int) auth('api')->id()) {
            return $this->error('Only the receiver can approve this request', [], 403);
        }

        $conversationRequest->update(['status' => 'approved']);

        return $this->success('Conversation request approved', [
            'conversation' => $this->createOrGetPrivateConversation(
                $conversationRequest->sender_id,
                $conversationRequest->receiver_id
            ),
        ]);
    }

    public function reject(int $request)
    {
        $conversationRequest = ConversationRequest::findOrFail($request);

        if ((int) $conversationRequest->receiver_id !== (int) auth('api')->id()) {
            return $this->error('Only the receiver can reject this request', [], 403);
        }

        $conversationRequest->update(['status' => 'rejected']);

        return $this->success('Conversation request rejected', $conversationRequest->load('sender:id,name,email,is_online,last_seen_at', 'receiver:id,name,email,is_online,last_seen_at'));
    }

    private function findRequestBetween(int $firstUserId, int $secondUserId): ?ConversationRequest
    {
        return ConversationRequest::query()
            ->where(function ($query) use ($firstUserId, $secondUserId) {
                $query->where('sender_id', $firstUserId)
                    ->where('receiver_id', $secondUserId);
            })
            ->orWhere(function ($query) use ($firstUserId, $secondUserId) {
                $query->where('sender_id', $secondUserId)
                    ->where('receiver_id', $firstUserId);
            })
            ->latest()
            ->first();
    }

    private function findActiveRequestBetween(int $firstUserId, int $secondUserId): ?ConversationRequest
    {
        return ConversationRequest::query()
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($query) use ($firstUserId, $secondUserId) {
                $query->where(function ($builder) use ($firstUserId, $secondUserId) {
                    $builder->where('sender_id', $firstUserId)
                        ->where('receiver_id', $secondUserId);
                })->orWhere(function ($builder) use ($firstUserId, $secondUserId) {
                    $builder->where('sender_id', $secondUserId)
                        ->where('receiver_id', $firstUserId);
                });
            })
            ->latest()
            ->first();
    }

    private function createOrGetPrivateConversation(int $firstUserId, int $secondUserId): Conversation
    {
        $conversation = Conversation::query()
            ->where('type', 'private')
            ->whereHas('users', fn ($query) => $query->where('users.id', $firstUserId))
            ->whereHas('users', fn ($query) => $query->where('users.id', $secondUserId))
            ->withCount('participants')
            ->get()
            ->firstWhere('participants_count', 2);

        if (! $conversation) {
            $conversation = Conversation::create(['type' => 'private']);
            $conversation->users()->attach([
                $firstUserId => ['role' => 'member'],
                $secondUserId => ['role' => 'member'],
            ]);
        }

        return $conversation->load('participants.user:id,name,email,is_online,last_seen_at', 'users:id,name,email,is_online,last_seen_at');
    }
}
