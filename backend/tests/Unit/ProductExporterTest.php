<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Catalog\ProductExporter;
use App\Entity\Product;
use App\Entity\ProductAttribute;
use App\Entity\ProductImage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\TestCase;

final class ProductExporterTest extends TestCase
{
    public function testSplitsAttributesIntoColumnsWithReadableWidths(): void
    {
        $product = new Product('code-1', 'Бермуды', 'Длинное описание', '1320.00', '33.33');
        $product->addAttribute(new ProductAttribute('Размер', '46(M)'));
        $product->addAttribute(new ProductAttribute('Бренд', 'OMSA'));
        $product->addImage(new ProductImage('http://catalog.example/a.jpg', 'images/a.jpg', 0));
        $product->addImage(new ProductImage('http://catalog.example/b.jpg', 'images/b.jpg', 1));

        $path = (new ProductExporter())->xlsx([$product]);
        try {
            $sheet = IOFactory::load($path)->getActiveSheet();
            self::assertSame(
                ['Внешний код', 'Наименование', 'Описание', 'Цена продажи', 'Скидка %', 'Изображения', 'Доп. поле: Бренд', 'Доп. поле: Размер'],
                $sheet->rangeToArray('A1:H1', null, false, false)[0],
            );
            self::assertSame('code-1', $sheet->getCell('A2')->getValue());
            self::assertSame('1320.00', $sheet->getCell('D2')->getValue());
            self::assertSame('33.33', $sheet->getCell('E2')->getValue());
            self::assertSame("http://catalog.example/a.jpg\nhttp://catalog.example/b.jpg", $sheet->getCell('F2')->getValue());
            self::assertSame('OMSA', $sheet->getCell('G2')->getValue());
            self::assertSame('46(M)', $sheet->getCell('H2')->getValue());
            self::assertGreaterThan(20, $sheet->getColumnDimension('A')->getWidth());
            self::assertGreaterThan(40, $sheet->getColumnDimension('B')->getWidth());
            self::assertGreaterThan(20, $sheet->getColumnDimension('G')->getWidth());
            self::assertTrue($sheet->getStyle('C2')->getAlignment()->getWrapText());
            self::assertSame('A2', $sheet->getFreezePane());
        } finally {
            @unlink($path);
        }
    }

    public function testWritesFormulaLikeCellsAsText(): void
    {
        $formula = '=HYPERLINK("http://evil.example","click")';
        $product = new Product('=CODE', $formula, $formula, '10.00', '0.00');
        $product->addAttribute(new ProductAttribute('=поле', $formula));
        $product->addImage(new ProductImage($formula, 'images/a.jpg', 0));

        $path = (new ProductExporter())->xlsx([$product]);
        try {
            $sheet = IOFactory::load($path)->getActiveSheet();
            self::assertSame(
                ['Внешний код', 'Наименование', 'Описание', 'Цена продажи', 'Скидка %', 'Изображения', 'Доп. поле: =поле'],
                $sheet->rangeToArray('A1:G1', null, false, false)[0],
            );
            foreach (['A1', 'G1', 'A2', 'B2', 'C2', 'D2', 'E2', 'F2', 'G2'] as $cell) {
                self::assertSame(DataType::TYPE_STRING, $sheet->getCell($cell)->getDataType(), $cell);
            }
            self::assertSame($formula, $sheet->getCell('B2')->getValue());
            self::assertSame($formula, $sheet->getCell('F2')->getValue());
            self::assertSame('Доп. поле: =поле', $sheet->getCell('G1')->getValue());
        } finally {
            @unlink($path);
        }
    }
}
