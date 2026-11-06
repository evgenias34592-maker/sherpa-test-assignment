<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Import\SpreadsheetParser;
use App\Import\SpreadsheetStructureException;
use PHPUnit\Framework\TestCase;

final class SpreadsheetParserTest extends TestCase
{
    public function testReadsTheAttachedCatalog(): void
    {
        $rows = (new SpreadsheetParser())->parse(dirname(__DIR__) . '/fixtures/import-example.xlsx');

        self::assertCount(40, $rows);
        self::assertSame('3UHfAid1jaMiwgBuNvnsf3', $rows[0]->externalCode);
        self::assertContains($rows[0]->priceRaw, ['1320,00', '1320.00']);
        self::assertContains($rows[0]->purchaseRaw, ['880,00', '880.00']);
        $attributes = [];
        foreach ($rows[0]->attributes as $attribute) {
            $attributes[$attribute['key']] = $attribute['value'];
        }
        self::assertSame('46(M)', $attributes['Размер']);
        self::assertSame('OMSA', $attributes['Бренд']);
        self::assertNotContains('Доп. поле: Ссылки на фото', array_keys($attributes));
        self::assertSame('400', $attributes['Вес упаковки(г)']);
        self::assertContains('http://catalog.collant.ru/pics/SNL-504038_m.jpg', $rows[0]->imageUrls);
        self::assertContains('http://catalog.collant.ru/pics/SNL-504038_p.jpg', $rows[0]->imageUrls);
        self::assertNotContains('400', $rows[0]->imageUrls);
        self::assertCount(4, $rows[0]->imageUrls);
    }

    public function testParsesSampleWithinUncompressedLimit(): void
    {
        $rows = (new SpreadsheetParser())->parse(dirname(__DIR__, 3) . '/samples/import-example.xlsx');

        self::assertCount(40, $rows);
        self::assertSame('3UHfAid1jaMiwgBuNvnsf3', $rows[0]->externalCode);
    }

    public function testRejectsArchiveBeforeInflatingIt(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        self::assertIsString($path);
        $target = $path . '.xlsx';
        $zip = new \ZipArchive();
        $zip->open($target, \ZipArchive::CREATE);
        $zip->addFromString('xl/workbook.xml', str_repeat('A', 8000));
        $zip->close();
        unlink($path);

        try {
            (new SpreadsheetParser(1000))->parse($target);
            self::fail('Expanded archive must be rejected before parsing');
        } catch (SpreadsheetStructureException $exception) {
            self::assertSame('Архив xlsx превышает допустимый размер в распакованном виде', $exception->getMessage());
        } finally {
            unlink($target);
        }
    }

    public function testMarksRowWithFormulaAsRejected(): void
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $headers = ['Внешний код', 'Наименование', 'Описание', 'Цена: Цена продажи', 'Закупочная цена'];
        foreach ($headers as $index => $header) {
            $sheet->setCellValue([$index + 1, 1], $header);
        }
        $sheet->fromArray(['f-1', 'Товар с формулой', 'Описание', '100,00', '50,00'], null, 'A2');
        $sheet->setCellValue('D3', '=A2+1');
        $sheet->setCellValue('A3', 'f-2');
        $sheet->setCellValue('B3', 'Ещё товар');
        $sheet->setCellValue('E3', '10,00');
        $path = tempnam(sys_get_temp_dir(), 'formula') . '.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        try {
            $rows = (new SpreadsheetParser())->parse($path);
            self::assertCount(2, $rows);
            self::assertNull($rows[0]->rejection);
            self::assertSame('Формулы в ячейках не поддерживаются', $rows[1]->rejection);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
