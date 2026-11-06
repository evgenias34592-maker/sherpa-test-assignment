<?php

declare(strict_types=1);

namespace App\Http;

final class ApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $status,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    public function status(): int
    {
        return $this->status;
    }
}
