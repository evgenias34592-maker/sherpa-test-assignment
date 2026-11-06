<?php

declare(strict_types=1);

namespace App\Security;

use Doctrine\DBAL\Connection;

final class DatabaseRateLimitStore implements RateLimitStore
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function hit(string $bucket, int $now, int $windowSeconds, int $limit): RateLimitResult
    {
        return $this->connection->transactional(function () use ($bucket, $now, $windowSeconds, $limit): RateLimitResult {
            $this->connection->executeStatement(
                'DELETE FROM rate_limit_hits WHERE hit_at < :cutoff',
                ['cutoff' => $now - 86400],
            );
            $since = $now - $windowSeconds;
            $count = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM rate_limit_hits WHERE bucket = :bucket AND hit_at > :since',
                ['bucket' => $bucket, 'since' => $since],
            );
            $current = is_numeric($count) ? (int) $count : 0;
            if ($current >= $limit) {
                $oldest = $this->connection->fetchOne(
                    'SELECT MIN(hit_at) FROM rate_limit_hits WHERE bucket = :bucket AND hit_at > :since',
                    ['bucket' => $bucket, 'since' => $since],
                );
                $oldestAt = is_numeric($oldest) ? (int) $oldest : $now;
                $retry = max(1, $oldestAt + $windowSeconds - $now);

                return new RateLimitResult(false, $retry, 0);
            }

            $this->connection->insert('rate_limit_hits', [
                'bucket' => $bucket,
                'hit_at' => $now,
            ]);

            return new RateLimitResult(true, 0, max(0, $limit - $current - 1));
        });
    }
}
