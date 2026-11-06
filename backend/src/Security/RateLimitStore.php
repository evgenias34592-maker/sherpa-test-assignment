<?php

declare(strict_types=1);

namespace App\Security;

interface RateLimitStore
{
    public function hit(string $bucket, int $now, int $windowSeconds, int $limit): RateLimitResult;
}
