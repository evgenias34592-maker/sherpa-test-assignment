<?php

declare(strict_types=1);

namespace App\Catalog;

use App\Http\ApiException;
use App\Import\MoneyParser;
use App\Import\RowImportException;

final class ProductQuery
{
    public function __construct(
        public readonly int $page,
        public readonly int $limit,
        public readonly string $name,
        public readonly ?string $priceMin,
        public readonly ?string $priceMax,
    ) {
    }

    /** @param array<string, mixed> $query */
    public static function fromArray(array $query, MoneyParser $money): self
    {
        $page = self::positiveInt($query['page'] ?? 1, 1);
        $limit = self::positiveInt($query['limit'] ?? 20, 20);
        if ($limit > 100) {
            $limit = 100;
        }
        $name = trim((string) ($query['name'] ?? ''));
        if (mb_strlen($name) > 200) {
            throw new ApiException('Слишком длинный поисковый запрос', 422);
        }
        $priceMin = self::price($query['priceMin'] ?? $query['price_min'] ?? null, $money, 'priceMin');
        $priceMax = self::price($query['priceMax'] ?? $query['price_max'] ?? null, $money, 'priceMax');
        if ($priceMin !== null && $priceMax !== null && (float) $priceMin > (float) $priceMax) {
            throw new ApiException('Минимальная цена больше максимальной', 422);
        }

        return new self($page, $limit, $name, $priceMin, $priceMax);
    }

    private static function positiveInt(mixed $value, int $default): int
    {
        if ($value === null || $value === '') {
            return $default;
        }
        if (is_int($value) && $value > 0) {
            return $value;
        }
        if (is_string($value) && preg_match('/^[1-9]\d*$/', $value) === 1) {
            return (int) $value;
        }

        throw new ApiException('Некорректные параметры пагинации', 422);
    }

    private static function price(mixed $value, MoneyParser $money, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            throw new ApiException('Некорректное значение ' . $field, 422);
        }
        try {
            $parsed = $money->parse($value);
        } catch (RowImportException) {
            throw new ApiException('Некорректное значение ' . $field, 422);
        }
        if ($parsed->cents < 0) {
            throw new ApiException('Цена не может быть отрицательной', 422);
        }

        return $parsed->format();
    }
}
