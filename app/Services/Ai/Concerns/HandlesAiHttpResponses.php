<?php

namespace App\Services\Ai\Concerns;

use App\Exceptions\AiProviderException;
use Illuminate\Http\Client\Response;
use Throwable;

trait HandlesAiHttpResponses
{
    private function requireApiKey(?string $apiKey, string $provider): string
    {
        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new AiProviderException(
                "Missing API key for {$provider}.",
                $provider,
                500,
            );
        }

        return $apiKey;
    }

    private function ensureSuccessful(Response $response, string $provider): array
    {
        $data = $response->json();

        if ($response->successful() && is_array($data)) {
            return $data;
        }

        throw new AiProviderException(
            $this->extractErrorMessage($data) ?? "{$provider} API request failed.",
            $provider,
            $this->statusForUpstream($response->status()),
            is_array($data) ? $data : null,
        );
    }

    /**
     * @return array{verify?: string}
     */
    private function httpOptions(): array
    {
        $caBundle = config('services.ai.ca_bundle');

        if (! is_string($caBundle) || trim($caBundle) === '') {
            return [];
        }

        return ['verify' => $caBundle];
    }

    private function providerConnectionException(Throwable $exception, string $provider): AiProviderException
    {
        $message = $exception->getMessage();

        if (str_contains($message, 'cURL error 60') || str_contains($message, 'SSL certificate problem')) {
            $message = 'SSL certificate verification failed. Set AI_CA_BUNDLE to a valid cacert.pem path, or configure curl.cainfo and openssl.cafile in your PHP php.ini.';
        }

        return new AiProviderException($message, $provider, 502);
    }

    private function extractErrorMessage(mixed $data): ?string
    {
        if (! is_array($data)) {
            return null;
        }

        $message = data_get($data, 'error.message')
            ?? data_get($data, 'error.details.0.reason')
            ?? data_get($data, 'message');

        return is_string($message) && $message !== '' ? $message : null;
    }

    private function statusForUpstream(int $upstreamStatus): int
    {
        return match ($upstreamStatus) {
            400, 401, 403, 404, 408, 409, 422, 429 => $upstreamStatus,
            default => 502,
        };
    }
}
