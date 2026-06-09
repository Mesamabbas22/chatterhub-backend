<?php

use App\Http\Controllers\AiChatController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\CommunityConversationController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\ConversationRequestController;
use App\Http\Controllers\MessageController;

Route::post('/chat', [AiChatController::class, 'chat']);
Route::post('/chat/{provider}', [AiChatController::class, 'chat'])
    ->whereIn('provider', ['openai', 'claude', 'gemini']);

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:api')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::post('/conversations/private', [ConversationController::class, 'startPrivateChat']);
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show']);

    Route::get('/conversation-users/search', [ConversationRequestController::class, 'searchUsers']);
    Route::post('/conversation-requests', [ConversationRequestController::class, 'store']);
    Route::get('/conversation-requests/received', [ConversationRequestController::class, 'received']);
    Route::post('/conversation-requests/{request}/approve', [ConversationRequestController::class, 'approve']);
    Route::post('/conversation-requests/{request}/reject', [ConversationRequestController::class, 'reject']);

    Route::get('/conversations/{conversation}/messages', [MessageController::class, 'index']);
    Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store']);

    Route::get('/communities', [CommunityController::class, 'index']);
    Route::post('/communities', [CommunityController::class, 'store']);
    Route::get('/communities/{community}', [CommunityController::class, 'show']);
    Route::post('/communities/{community}/join', [CommunityController::class, 'join']);
    Route::post('/communities/{community}/leave', [CommunityController::class, 'leave']);

    Route::get('/communities/{community}/conversations', [CommunityConversationController::class, 'index']);
    Route::post('/communities/{community}/conversations', [CommunityConversationController::class, 'store']);
});
Broadcast::routes(['middleware' => ['auth:api']]);

