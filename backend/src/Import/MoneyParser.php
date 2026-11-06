<?php

declare(strict_types=1);

namespace App\Import;

final class MoneyParser
{
    public function parse(string|int|float $raw): Money
    {
        if (is_int($raw)) {
            return new Money($raw * 100);
        }

        if (is_float($raw)) {
            if (!is_finite($raw)) {
                throw new RowImportException('Некорректная сумма');
            }

            return $this->parse(number_format($raw, 2, '.', ''));
        }

        $normalized = str_replace(["\u{00A0}", ' '], '', trim($raw));
        $normalized = str_replace(',', '.', $normalized);
        if ($normalized === '' || !preg_match('/^(-)?(\d+)(?:\.(\d+))?$/', $normalized, $matches)) {
            throw new RowImportException('Некорректная сумма');
        }

        $negative = $matches[1] === '-';
        $whole = $matches[2];
        $fraction = $matches[3] ?? '';
        if (strlen($whole) > 10) {
            throw new RowImportException('Сумма слишком большая');
        }

        $fraction = substr($fraction . '000', 0, 3);
        $cents = ((int) $whole) * 100 + (int) substr($fraction, 0, 2);
        if ((int) $fraction[2] >= 5) {
            ++$cents;
        }

        if ($negative) {
            $cents = -$cents;
        }

        return new Money($cents);
    }
}
