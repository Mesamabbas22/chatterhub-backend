<?php

namespace App\Exceptions;

use RuntimeException;

class AiProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $provider,
        public readonly int $status = 502,
        public readonly ?array $context = null,
    ) {
        parent::__construct($message);
    }
}
