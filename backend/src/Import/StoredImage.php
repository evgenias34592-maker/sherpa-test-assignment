<?php

declare(strict_types=1);

namespace App\Import;

final class StoredImage
{
    public function __construct(
        public readonly string $sourceUrl,
        public readonly string $relativePath,
        public readonly bool $created,
    ) {
    }
}
