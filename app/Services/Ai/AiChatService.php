<?php

namespace App\Services\Ai;

use InvalidArgumentException;

class AiChatService
{
    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array{model?: string, temperature?: float|int, max_tokens?: int}  $options
     * @return array{provider: string, model: string, message: string, usage: array|null}
     */
    public function send(string $provider, array $messages, array $options = []): array
    {
        return $this->provider($provider)->send($messages, $options);
    }

    private function provider(string $provider): AiChatProvider
    {
        return match ($provider) {
            'openai' => new OpenAiChatProvider,
            'claude' => new ClaudeChatProvider,
            'gemini' => new GeminiChatProvider,
            default => throw new InvalidArgumentException("Unsupported AI provider [{$provider}]."),
        };
    }
}
