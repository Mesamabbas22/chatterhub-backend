<?php

namespace App\Services\Ai;

use App\Exceptions\AiProviderException;
use App\Services\Ai\Concerns\HandlesAiHttpResponses;
use Illuminate\Support\Facades\Http;
use Throwable;

class GeminiChatProvider implements AiChatProvider
{
    use HandlesAiHttpResponses;

    public function name(): string
    {
        return 'gemini';
    }

    public function send(array $messages, array $options = []): array
    {
        $apiKey = $this->requireApiKey(config('services.ai.gemini.key'), $this->name());
        $model = (string) ($options['model'] ?? config('services.ai.gemini.model'));
        [$system, $contents] = $this->formatMessages($messages);

        $payload = [
            'contents' => $contents,
        ];

        if ($system !== '') {
            $payload['systemInstruction'] = [
                'parts' => [['text' => $system]],
            ];
        }

        $generationConfig = [];

        if (array_key_exists('temperature', $options)) {
            $generationConfig['temperature'] = $options['temperature'];
        }

        if (array_key_exists('max_tokens', $options)) {
            $generationConfig['maxOutputTokens'] = $options['max_tokens'];
        }

        if ($generationConfig !== []) {
            $payload['generationConfig'] = $generationConfig;
        }

        $url = sprintf(
            '%s/models/%s:generateContent',
            rtrim((string) config('services.ai.gemini.base_url'), '/'),
            rawurlencode($model),
        );

        try {
            $response = Http::timeout((int) config('services.ai.timeout'))
                ->withOptions($this->httpOptions())
                ->acceptJson()
                ->withQueryParameters(['key' => $apiKey])
                ->post($url, $payload);
        } catch (Throwable $exception) {
            throw $this->providerConnectionException($exception, $this->name());
        }

        $data = $this->ensureSuccessful($response, $this->name());
        $message = $this->extractText($data);

        if ($message === '') {
            throw new AiProviderException('Gemini returned an empty response.', $this->name(), 502, $data);
        }

        return [
            'provider' => $this->name(),
            'model' => $model,
            'message' => $message,
            'usage' => data_get($data, 'usageMetadata'),
        ];
    }

    private function formatMessages(array $messages): array
    {
        $system = [];
        $contents = [];

        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                $system[] = $message['content'];

                continue;
            }

            $contents[] = [
                'role' => $message['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $message['content']]],
            ];
        }

        return [trim(implode("\n\n", $system)), $contents];
    }

    private function extractText(array $data): string
    {
        $parts = [];

        foreach ((array) data_get($data, 'candidates.0.content.parts', []) as $part) {
            $text = data_get($part, 'text');

            if (is_string($text) && $text !== '') {
                $parts[] = $text;
            }
        }

        return trim(implode("\n", $parts));
    }
}
