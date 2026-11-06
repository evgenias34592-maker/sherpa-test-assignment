<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Http\ApiException;
use App\Import\FileValidator;
use App\Import\XlsxZipLimit;
use PHPUnit\Framework\TestCase;

final class FileValidatorTest extends TestCase
{
    public function testAcceptsSampleWorkbook(): void
    {
        $path = dirname(__DIR__) . '/fixtures/import-example.xlsx';
        $validator = new FileValidator(10_485_760);

        $validator->assertXlsx(
            $path,
            'import example.xlsx',
            (int) filesize($path),
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );

        $this->expectNotToPerformAssertions();
    }

    public function testAcceptsSampleArchive(): void
    {
        $path = dirname(__DIR__, 3) . '/samples/import-example.xlsx';
        $validator = new FileValidator(10_485_760);

        $validator->assertXlsx($path, 'import-example.xlsx', (int) filesize($path), null);
        self::assertFalse(XlsxZipLimit::exceeds($path, XlsxZipLimit::MAX_UNCOMPRESSED_BYTES));
        self::assertTrue(XlsxZipLimit::exceeds($path, 50_000));
        self::assertFalse(XlsxZipLimit::exceeds($path, 200_000));
    }

    public function testRejectsRenamedTextFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bad');
        self::assertIsString($path);
        file_put_contents($path, 'not an excel file');
        $validator = new FileValidator(10_485_760);

        try {
            $validator->assertXlsx($path, 'goods.xlsx', (int) filesize($path), 'text/plain');
            self::fail('Text file must be rejected');
        } catch (ApiException $exception) {
            self::assertSame(422, $exception->status());
        } finally {
            unlink($path);
        }
    }

    public function testRejectsOversizedFile(): void
    {
        $path = dirname(__DIR__) . '/fixtures/import-example.xlsx';
        $this->expectException(ApiException::class);
        (new FileValidator(100))->assertXlsx($path, 'import.xlsx', 101, null);
    }

    public function testRejectsArchiveThatExpandsPastTheLimit(): void
    {
        $path = $this->archive(str_repeat('A', 5000));
        try {
            (new FileValidator(10_485_760, 1000))->assertXlsx(
                $path,
                'bomb.xlsx',
                (int) filesize($path),
                'application/zip',
            );
            self::fail('Expanded archive must be rejected');
        } catch (ApiException $exception) {
            self::assertSame(413, $exception->status());
        } finally {
            unlink($path);
        }
    }

    private function archive(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        self::assertIsString($path);
        $target = $path . '.xlsx';
        $zip = new \ZipArchive();
        $zip->open($target, \ZipArchive::CREATE);
        $zip->addFromString('xl/workbook.xml', $contents);
        $zip->close();
        unlink($path);

        return $target;
    }
}
