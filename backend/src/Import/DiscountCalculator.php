<?php

declare(strict_types=1);

namespace App\Import;

final class DiscountCalculator
{
    public function percent(Money $sale, Money $purchase): string
    {
        if ($sale->cents <= 0) {
            throw new RowImportException('Цена продажи должна быть больше нуля');
        }
        if ($purchase->cents < 0) {
            throw new RowImportException('Закупочная цена не может быть отрицательной');
        }

        $diff = $sale->cents - $purchase->cents;
        $negative = $diff < 0;
        $numerator = abs($diff) * 10000;
        $rounded = intdiv($numerator + intdiv($sale->cents, 2), $sale->cents);
        $scaled = $negative ? -$rounded : $rounded;
        $sign = $scaled < 0 ? '-' : '';
        $absolute = abs($scaled);

        return $sign
            . intdiv($absolute, 100)
            . '.'
            . str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }
}
