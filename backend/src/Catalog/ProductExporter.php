<?php

declare(strict_types=1);

namespace App\Catalog;

use App\Entity\Product;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class ProductExporter
{
    /** @param list<Product> $products */
    public function xlsx(array $products): string
    {
        $attributeKeys = $this->attributeKeys($products);
        $headers = ['Внешний код', 'Наименование', 'Описание', 'Цена продажи', 'Скидка %', 'Изображения'];
        foreach ($attributeKeys as $key) {
            $headers[] = 'Доп. поле: ' . $key;
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Товары');
        foreach ($headers as $index => $header) {
            $this->writeText($sheet, $index + 1, 1, $header);
        }

        $rowNumber = 2;
        foreach ($products as $product) {
            $values = [];
            foreach ($product->attributes() as $attribute) {
                $values[$attribute->key()] = $attribute->value();
            }
            $urls = [];
            foreach ($product->images() as $image) {
                $urls[] = $image->url();
            }
            $this->writeText($sheet, 1, $rowNumber, $product->externalCode());
            $this->writeText($sheet, 2, $rowNumber, $product->name());
            $this->writeText($sheet, 3, $rowNumber, $product->description());
            $this->writeText($sheet, 4, $rowNumber, $product->price());
            $this->writeText($sheet, 5, $rowNumber, $product->discount());
            $this->writeText($sheet, 6, $rowNumber, implode("\n", $urls));
            foreach ($attributeKeys as $offset => $key) {
                if (isset($values[$key])) {
                    $this->writeText($sheet, $offset + 7, $rowNumber, $values[$key]);
                }
            }
            ++$rowNumber;
        }

        $this->layout($sheet, count($headers), max(1, $rowNumber - 1), $attributeKeys);
        $temporary = tempnam(sys_get_temp_dir(), 'export');
        if ($temporary === false) {
            throw new \RuntimeException('Cannot create export file');
        }
        $target = $temporary . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($target);
        $spreadsheet->disconnectWorksheets();
        @unlink($temporary);

        return $target;
    }

    private function writeText(Worksheet $sheet, int $column, int $row, string $value): void
    {
        $sheet->setCellValueExplicit([$column, $row], $value, DataType::TYPE_STRING);
    }

    /**
     * @param list<Product> $products
     *
     * @return list<string>
     */
    private function attributeKeys(array $products): array
    {
        $keys = [];
        foreach ($products as $product) {
            foreach ($product->attributes() as $attribute) {
                $keys[$attribute->key()] = true;
            }
        }
        $names = array_keys($keys);
        sort($names, SORT_STRING);

        return $names;
    }

    /**
     * @param list<string> $attributeKeys
     */
    private function layout(Worksheet $sheet, int $columns, int $lastRow, array $attributeKeys): void
    {
        foreach ([24.0, 42.0, 56.0, 16.0, 12.0, 44.0] as $index => $width) {
            $sheet->getColumnDimensionByColumn($index + 1)->setWidth($width);
        }
        foreach ($attributeKeys as $offset => $key) {
            $length = mb_strlen('Доп. поле: ' . $key) + 4;
            $sheet->getColumnDimensionByColumn($offset + 7)->setWidth((float) min(40, max(22, $length)));
        }

        $header = $sheet->getStyle([1, 1, $columns, 1]);
        $header->getFont()->setBold(true);
        $header->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:' . Coordinate::stringFromColumnIndex($columns) . $lastRow);
        if ($lastRow < 2) {
            return;
        }
        $sheet->getStyle([1, 2, $columns, $lastRow])
            ->getAlignment()
            ->setVertical(Alignment::VERTICAL_TOP)
            ->setWrapText(true);
    }
}
