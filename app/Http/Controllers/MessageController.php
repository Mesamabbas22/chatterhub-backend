<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MessageController extends Controller
{
    public function index(Conversation $conversation)
    {
        if (! $this->isParticipant($conversation)) {
            return $this->error('You are not a participant of this conversation', [], 403);
        }

        $messages = $conversation->messages()
            ->with('sender:id,name,email')
            ->oldest()
            ->paginate(50);

        return $this->success('Messages fetched successfully', $messages);
    }

    public function store(Request $request, Conversation $conversation)
    {
        if (! $this->isParticipant($conversation)) {
            return $this->error('You are not a participant of this conversation', [], 403);
        }

        $validated = $request->validate([
            'message_type' => ['sometimes', Rule::in(['text', 'image', 'file'])],
            'message' => [
                Rule::requiredIf(fn () => $request->input('message_type', 'text') === 'text'),
                'nullable',
                'string',
            ],
        ]);

        $message = $conversation->messages()->create([
            'sender_id' => auth('api')->id(),
            'message' => $validated['message'] ?? null,
            'message_type' => $validated['message_type'] ?? 'text',
        ]);

        $conversation->update(['last_message_at' => now()]);

        return $this->success('Message sent successfully', $message->load('sender:id,name,email'), 201);
    }

    private function isParticipant(Conversation $conversation): bool
    {
        return $conversation->participants()
            ->where('user_id', auth('api')->id())
            ->exists();
    }
}
