<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Import\MoneyParser;
use App\Import\RowImportException;
use PHPUnit\Framework\TestCase;

final class MoneyParserTest extends TestCase
{
    private MoneyParser $parser;

    protected function setUp(): void
    {
        $this->parser = new MoneyParser();
    }

    public function testParsesCommaAndSpace(): void
    {
        self::assertSame('1320.00', $this->parser->parse('1 320,00')->format());
        self::assertSame('1320.50', $this->parser->parse('1320.5')->format());
        self::assertSame('10.13', $this->parser->parse('10,125')->format());
    }

    public function testRejectsText(): void
    {
        $this->expectException(RowImportException::class);
        $this->parser->parse('бесплатно');
    }
}
