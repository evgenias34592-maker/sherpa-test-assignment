<?php

declare(strict_types=1);

namespace App\Import;

final class ParsedRow
{
    /**
     * @param list<array{key: string, value: string}> $attributes
     * @param list<string> $imageUrls
     */
    public function __construct(
        public readonly int $number,
        public readonly string $externalCode,
        public readonly string $name,
        public readonly string $description,
        public readonly string $priceRaw,
        public readonly string $purchaseRaw,
        public readonly array $attributes,
        public readonly array $imageUrls,
        public readonly ?string $rejection = null,
    ) {
    }
}
