<?php

declare(strict_types=1);

namespace App\Security;

final class RateLimitResult
{
    public function __construct(
        public readonly bool $allowed,
        public readonly int $retryAfter,
        public readonly int $remaining,
    ) {
    }
}
