<?php

declare(strict_types=1);

namespace App\Security;

final class ArrayRateLimitStore implements RateLimitStore
{
    /** @var array<string, list<int>> */
    private array $hits = [];

    public function hit(string $bucket, int $now, int $windowSeconds, int $limit): RateLimitResult
    {
        $since = $now - $windowSeconds;
        $kept = [];
        foreach ($this->hits[$bucket] ?? [] as $hitAt) {
            if ($hitAt > $since) {
                $kept[] = $hitAt;
            }
        }
        if (count($kept) >= $limit) {
            $oldest = min($kept);
            $this->hits[$bucket] = $kept;

            return new RateLimitResult(false, max(1, $oldest + $windowSeconds - $now), 0);
        }
        $kept[] = $now;
        $this->hits[$bucket] = $kept;

        return new RateLimitResult(true, 0, $limit - count($kept));
    }
}
