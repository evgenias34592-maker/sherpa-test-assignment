<?php

declare(strict_types=1);

namespace App\Import;

final class CheckedUrl
{
    public function __construct(
        public readonly string $original,
        public readonly string $scheme,
        public readonly string $host,
        public readonly int $port,
        public readonly string $pathAndQuery,
        public readonly string $ip,
    ) {
    }
}
