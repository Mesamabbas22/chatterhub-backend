<?php

namespace App\Services\Ai;

interface AiChatProvider
{
    public function name(): string;

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array{model?: string, temperature?: float|int, max_tokens?: int}  $options
     * @return array{provider: string, model: string, message: string, usage: array|null}
     */
    public function send(array $messages, array $options = []): array;
}
