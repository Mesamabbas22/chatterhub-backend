<?php

namespace App\Http\Controllers;

use App\Exceptions\AiProviderException;
use App\Services\Ai\AiChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class AiChatController extends Controller
{
    public function __construct(private readonly AiChatService $aiChatService)
    {
    }

    public function chat(Request $request, ?string $provider = null): JsonResponse
    {
        $data = $request->validate([
            'provider' => [
                Rule::requiredIf($provider === null),
                'string',
                Rule::in(['openai', 'claude', 'gemini']),
            ],
            'message' => ['required_without:messages', 'string', 'max:20000'],
            'messages' => ['required_without:message', 'array', 'min:1', 'max:50'],
            'messages.*.role' => ['required_with:messages', 'string', Rule::in(['system', 'user', 'assistant'])],
            'messages.*.content' => ['required_with:messages', 'string', 'max:20000'],
            'model' => ['nullable', 'string', 'max:100'],
            'temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'max_tokens' => ['nullable', 'integer', 'min:1', 'max:8192'],
        ]);

        $provider ??= $data['provider'];
        $messages = $this->messagesFromRequest($data);
        $options = array_filter([
            'model' => $data['model'] ?? null,
            'temperature' => $data['temperature'] ?? null,
            'max_tokens' => $data['max_tokens'] ?? null,
        ], fn (mixed $value): bool => $value !== null);

        try {
            return response()->json([
                'success' => true,
                'data' => $this->aiChatService->send($provider, $messages, $options),
            ]);
        } catch (AiProviderException $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'error' => [
                    'provider' => $exception->provider,
                    'message' => $exception->getMessage(),
                ],
            ], $exception->status);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => $exception->getMessage(),
                ],
            ], 422);
        }
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    private function messagesFromRequest(array $data): array
    {
        $messages = $data['messages'] ?? [];

        if (isset($data['message'])) {
            $messages[] = [
                'role' => 'user',
                'content' => $data['message'],
            ];
        }

        return $messages;
    }
}
