<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Conversation;

Broadcast::channel('conversation.{conversation_id}', function ($user, $conversationId) {
        return \DB::table('conversation_participants')
        ->where('conversation_id', $conversationId)
        ->where('user_id', $user->id)
        ->exists();
});
