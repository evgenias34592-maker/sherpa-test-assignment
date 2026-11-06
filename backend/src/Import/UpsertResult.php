<?php

declare(strict_types=1);

namespace App\Import;

final class UpsertResult
{
    /**
     * @param list<array{row: int, externalCode: string|null, message: string, level: string}> $issues
     */
    public function __construct(
        public readonly bool $created,
        public readonly array $issues,
    ) {
    }
}
