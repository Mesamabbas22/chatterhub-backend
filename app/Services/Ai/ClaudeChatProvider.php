<?php

namespace App\Services\Ai;

use App\Exceptions\AiProviderException;
use App\Services\Ai\Concerns\HandlesAiHttpResponses;
use Illuminate\Support\Facades\Http;
use Throwable;

class ClaudeChatProvider implements AiChatProvider
{
    use HandlesAiHttpResponses;

    public function name(): string
    {
        return 'claude';
    }

    public function send(array $messages, array $options = []): array
    {
        $apiKey = $this->requireApiKey(config('services.ai.claude.key'), $this->name());
        $model = (string) ($options['model'] ?? config('services.ai.claude.model'));
        [$system, $chatMessages] = $this->splitSystemMessages($messages);

        $payload = [
            'model' => $model,
            'max_tokens' => (int) ($options['max_tokens'] ?? 1024),
            'messages' => $chatMessages,
        ];

        if ($system !== '') {
            $payload['system'] = $system;
        }

        if (array_key_exists('temperature', $options)) {
            $payload['temperature'] = $options['temperature'];
        }

        try {
            $response = Http::timeout((int) config('services.ai.timeout'))
                ->withOptions($this->httpOptions())
                ->acceptJson()
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => (string) config('services.ai.claude.version'),
                ])
                ->post(rtrim((string) config('services.ai.claude.base_url'), '/').'/messages', $payload);
        } catch (Throwable $exception) {
            throw $this->providerConnectionException($exception, $this->name());
        }

        $data = $this->ensureSuccessful($response, $this->name());
        $message = $this->extractText($data);

        if ($message === '') {
            throw new AiProviderException('Claude returned an empty response.', $this->name(), 502, $data);
        }

        return [
            'provider' => $this->name(),
            'model' => (string) data_get($data, 'model', $model),
            'message' => $message,
            'usage' => data_get($data, 'usage'),
        ];
    }

    private function splitSystemMessages(array $messages): array
    {
        $system = [];
        $chatMessages = [];

        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                $system[] = $message['content'];

                continue;
            }

            $chatMessages[] = [
                'role' => $message['role'],
                'content' => $message['content'],
            ];
        }

        return [trim(implode("\n\n", $system)), $chatMessages];
    }

    private function extractText(array $data): string
    {
        $parts = [];

        foreach ((array) data_get($data, 'content', []) as $content) {
            $text = data_get($content, 'text');

            if (is_string($text) && $text !== '') {
                $parts[] = $text;
            }
        }

        return trim(implode("\n", $parts));
    }
}
