<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiChatApiTest extends TestCase
{
    public function test_openai_chat_returns_normalized_response(): void
    {
        config([
            'services.ai.openai.key' => 'test-openai-key',
            'services.ai.openai.model' => 'gpt-test',
        ]);

        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'model' => 'gpt-test',
                'output_text' => 'Hello from OpenAI',
                'usage' => ['input_tokens' => 5, 'output_tokens' => 7],
            ]),
        ]);

        $response = $this->postJson('/api/chat/openai', [
            'message' => 'Hello',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.provider', 'openai')
            ->assertJsonPath('data.message', 'Hello from OpenAI');
    }

    public function test_claude_chat_returns_normalized_response(): void
    {
        config([
            'services.ai.claude.key' => 'test-claude-key',
            'services.ai.claude.model' => 'claude-test',
        ]);

        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'model' => 'claude-test',
                'content' => [
                    ['type' => 'text', 'text' => 'Hello from Claude'],
                ],
                'usage' => ['input_tokens' => 5, 'output_tokens' => 7],
            ]),
        ]);

        $response = $this->postJson('/api/chat/claude', [
            'message' => 'Hello',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.provider', 'claude')
            ->assertJsonPath('data.message', 'Hello from Claude');
    }

    public function test_gemini_chat_returns_normalized_response(): void
    {
        config([
            'services.ai.gemini.key' => 'test-gemini-key',
            'services.ai.gemini.model' => 'gemini-test',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/v1beta/models/gemini-test:generateContent*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Hello from Gemini'],
                            ],
                        ],
                    ],
                ],
                'usageMetadata' => ['promptTokenCount' => 5, 'candidatesTokenCount' => 7],
            ]),
        ]);

        $response = $this->postJson('/api/chat/gemini', [
            'message' => 'Hello',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.provider', 'gemini')
            ->assertJsonPath('data.message', 'Hello from Gemini');
    }

    public function test_generic_chat_route_requires_provider(): void
    {
        $response = $this->postJson('/api/chat', [
            'message' => 'Hello',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('provider');
    }

    public function test_missing_provider_api_key_returns_json_error(): void
    {
        config(['services.ai.openai.key' => null]);

        $response = $this->postJson('/api/chat/openai', [
            'message' => 'Hello',
        ]);

        $response
            ->assertStatus(500)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.provider', 'openai');
    }

    public function test_upstream_errors_return_normalized_json_error(): void
    {
        config(['services.ai.openai.key' => 'test-openai-key']);

        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'error' => ['message' => 'Invalid API key'],
            ], 401),
        ]);

        $response = $this->postJson('/api/chat/openai', [
            'message' => 'Hello',
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.provider', 'openai')
            ->assertJsonPath('error.message', 'Invalid API key');
    }
}
