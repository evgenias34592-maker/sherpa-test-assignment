<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Import\DiscountCalculator;
use App\Import\Money;
use App\Import\RowImportException;
use PHPUnit\Framework\TestCase;

final class DiscountCalculatorTest extends TestCase
{
    public function testFormulaFromTheSample(): void
    {
        $discount = (new DiscountCalculator())->percent(new Money(132000), new Money(88000));

        self::assertSame('33.33', $discount);
    }

    public function testNegativeDiscountWhenPurchaseIsHigher(): void
    {
        $discount = (new DiscountCalculator())->percent(new Money(10000), new Money(15000));

        self::assertSame('-50.00', $discount);
    }

    public function testZeroSalePriceIsRejected(): void
    {
        $this->expectException(RowImportException::class);
        (new DiscountCalculator())->percent(new Money(0), new Money(0));
    }
}
