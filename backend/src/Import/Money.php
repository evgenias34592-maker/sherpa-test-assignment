<?php

declare(strict_types=1);

namespace App\Import;

final class Money
{
    public function __construct(public readonly int $cents)
    {
    }

    public function format(): string
    {
        $negative = $this->cents < 0;
        $absolute = abs($this->cents);

        return ($negative ? '-' : '')
            . intdiv($absolute, 100)
            . '.'
            . str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }
}
