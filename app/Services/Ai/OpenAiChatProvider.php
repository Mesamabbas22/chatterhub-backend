<?php

namespace App\Services\Ai;

use App\Exceptions\AiProviderException;
use App\Services\Ai\Concerns\HandlesAiHttpResponses;
use Illuminate\Support\Facades\Http;
use Throwable;

class OpenAiChatProvider implements AiChatProvider
{
    use HandlesAiHttpResponses;

    public function name(): string
    {
        return 'openai';
    }

    public function send(array $messages, array $options = []): array
    {
        $apiKey = $this->requireApiKey(config('services.ai.openai.key'), $this->name());
        $model = (string) ($options['model'] ?? config('services.ai.openai.model'));

        $payload = [
            'model' => $model,
            'input' => $this->formatMessages($messages),
        ];

        if (array_key_exists('temperature', $options)) {
            $payload['temperature'] = $options['temperature'];
        }

        if (array_key_exists('max_tokens', $options)) {
            $payload['max_output_tokens'] = $options['max_tokens'];
        }

        try {
            $response = Http::timeout((int) config('services.ai.timeout'))
                ->withOptions($this->httpOptions())
                ->acceptJson()
                ->withToken($apiKey)
                ->post(rtrim((string) config('services.ai.openai.base_url'), '/').'/responses', $payload);
        } catch (Throwable $exception) {
            throw $this->providerConnectionException($exception, $this->name());
        }

        $data = $this->ensureSuccessful($response, $this->name());
        $message = $this->extractText($data);

        if ($message === '') {
            throw new AiProviderException('OpenAI returned an empty response.', $this->name(), 502, $data);
        }

        return [
            'provider' => $this->name(),
            'model' => (string) data_get($data, 'model', $model),
            'message' => $message,
            'usage' => data_get($data, 'usage'),
        ];
    }

    private function formatMessages(array $messages): array
    {
        return array_map(fn (array $message): array => [
            'role' => $message['role'] === 'system' ? 'developer' : $message['role'],
            'content' => $message['content'],
        ], $messages);
    }

    private function extractText(array $data): string
    {
        $outputText = data_get($data, 'output_text');

        if (is_string($outputText) && $outputText !== '') {
            return $outputText;
        }

        $parts = [];

        foreach ((array) data_get($data, 'output', []) as $item) {
            foreach ((array) data_get($item, 'content', []) as $content) {
                $text = data_get($content, 'text');

                if (is_string($text) && $text !== '') {
                    $parts[] = $text;
                }
            }
        }

        return trim(implode("\n", $parts));
    }
}
