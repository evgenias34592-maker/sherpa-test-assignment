<?php

declare(strict_types=1);

namespace App\Import;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class SpreadsheetParser
{
    private const MAX_ROWS = 5000;
    private const MAX_COLUMNS = 200;

    public function __construct(private readonly int $maxUncompressedBytes = XlsxZipLimit::MAX_UNCOMPRESSED_BYTES)
    {
    }

    /** @return list<ParsedRow> */
    public function parse(string $path): array
    {
        if (XlsxZipLimit::exceeds($path, $this->maxUncompressedBytes)) {
            throw new SpreadsheetStructureException('Архив xlsx превышает допустимый размер в распакованном виде');
        }
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        $spreadsheet = $reader->load($path);
        try {
            return $this->read($spreadsheet->getActiveSheet());
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    /** @return list<ParsedRow> */
    private function read(Worksheet $sheet): array
    {
        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = $sheet->getHighestDataColumn();
        $columnCount = Coordinate::columnIndexFromString($highestColumn);
        if ($columnCount > self::MAX_COLUMNS) {
            throw new SpreadsheetStructureException('Слишком много столбцов');
        }
        if ($highestRow > self::MAX_ROWS + 1) {
            throw new SpreadsheetStructureException('Слишком много строк');
        }

        $headers = [];
        for ($column = 1; $column <= $columnCount; ++$column) {
            $headers[$column] = $this->cellText($sheet, $column, 1);
        }

        $map = [];
        foreach ($headers as $column => $header) {
            $normalized = $this->normalize($header);
            if ($normalized !== '' && !isset($map[$normalized])) {
                $map[$normalized] = $column;
            }
        }

        foreach (['внешний код', 'наименование', 'цена: цена продажи', 'закупочная цена'] as $required) {
            if (!isset($map[$required])) {
                throw new SpreadsheetStructureException('В файле нет столбца «' . $required . '»');
            }
        }

        $descriptionColumn = $map['описание'] ?? null;
        $rows = [];
        for ($rowNumber = 2; $rowNumber <= $highestRow; ++$rowNumber) {
            $values = [];
            $formula = false;
            foreach ($headers as $column => $header) {
                if ($this->normalize($header) === '') {
                    continue;
                }
                $read = $this->readCell($sheet, $column, $rowNumber);
                if ($read['formula']) {
                    $formula = true;
                }
                $values[$column] = $read['text'];
            }
            if ($this->isEmpty($values)) {
                continue;
            }

            $attributes = [];
            $imageUrls = [];
            foreach ($headers as $column => $header) {
                $normalized = $this->normalize($header);
                if ($normalized === '' || !str_starts_with($normalized, 'доп. поле')) {
                    continue;
                }
                $raw = $values[$column] ?? '';
                if ($this->isImageHeader($normalized)) {
                    foreach ($this->splitUrls($raw) as $url) {
                        $imageUrls[] = $url;
                    }
                    continue;
                }
                if ($raw === '') {
                    continue;
                }
                $attributes[] = [
                    'key' => $this->attributeKey($header),
                    'value' => $raw,
                ];
            }

            $rows[] = new ParsedRow(
                number: $rowNumber,
                externalCode: trim($values[$map['внешний код']] ?? ''),
                name: trim($values[$map['наименование']] ?? ''),
                description: $descriptionColumn === null ? '' : trim($values[$descriptionColumn] ?? ''),
                priceRaw: trim($values[$map['цена: цена продажи']] ?? ''),
                purchaseRaw: trim($values[$map['закупочная цена']] ?? ''),
                attributes: $this->uniqueAttributes($attributes),
                imageUrls: $this->uniqueUrls($imageUrls),
                rejection: $formula ? 'Формулы в ячейках не поддерживаются' : null,
            );
        }

        return $rows;
    }

    /** @param array<int, string> $values */
    private function isEmpty(array $values): bool
    {
        foreach ($values as $value) {
            if (trim($value) !== '') {
                return false;
            }
        }

        return true;
    }

    /** @return array{text: string, formula: bool} */
    private function readCell(Worksheet $sheet, int $column, int $row): array
    {
        $coordinate = Coordinate::stringFromColumnIndex($column) . $row;
        $cell = $sheet->getCell($coordinate);
        $value = $cell->getValue();
        if (is_string($value) && str_starts_with(ltrim($value), '=')) {
            return ['text' => '', 'formula' => true];
        }
        if ($value instanceof RichText) {
            return ['text' => trim($value->getPlainText()), 'formula' => false];
        }
        if ($value instanceof \DateTimeInterface) {
            return ['text' => $value->format('Y-m-d H:i:s'), 'formula' => false];
        }
        if (is_float($value)) {
            return ['text' => number_format($value, 2, '.', ''), 'formula' => false];
        }
        if (is_int($value)) {
            return ['text' => (string) $value, 'formula' => false];
        }
        if (is_bool($value)) {
            return ['text' => $value ? '1' : '0', 'formula' => false];
        }

        $text = trim((string) ($value ?? ''));
        $link = trim($cell->getHyperlink()->getUrl());
        if ($link !== '' && !str_contains($text, '://')) {
            $text = $link;
        }

        return ['text' => $text, 'formula' => false];
    }

    private function cellText(Worksheet $sheet, int $column, int $row): string
    {
        return $this->readCell($sheet, $column, $row)['text'];
    }

    private function normalize(string $value): string
    {
        $value = str_replace("\u{00A0}", ' ', trim($value));
        $collapsed = preg_replace('/\s+/u', ' ', $value);

        return mb_strtolower($collapsed ?? $value);
    }

    private function isImageHeader(string $normalized): bool
    {
        if (str_contains($normalized, 'фото') || str_contains($normalized, 'изображ')) {
            return true;
        }

        return str_contains($normalized, 'ссылк') && str_contains($normalized, 'упаковк');
    }

    private function attributeKey(string $header): string
    {
        $header = trim(str_replace("\u{00A0}", ' ', $header));
        $prefix = 'доп. поле';
        if (mb_strtolower(mb_substr($header, 0, mb_strlen($prefix))) === $prefix) {
            $rest = ltrim(trim(mb_substr($header, mb_strlen($prefix))), " :\t");
            if ($rest !== '') {
                return $rest;
            }
        }

        return $header;
    }

    /** @return list<string> */
    private function splitUrls(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }
        $parts = preg_split('/[\s,;|]+/u', trim($raw)) ?: [];
        $urls = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '') {
                $urls[] = $part;
            }
        }

        return $urls;
    }

    /**
     * @param list<array{key: string, value: string}> $attributes
     * @return list<array{key: string, value: string}>
     */
    private function uniqueAttributes(array $attributes): array
    {
        $byKey = [];
        foreach ($attributes as $attribute) {
            $byKey[$attribute['key']] = $attribute;
        }

        return array_values($byKey);
    }

    /** @param list<string> $urls
     * @return list<string>
     */
    private function uniqueUrls(array $urls): array
    {
        $unique = [];
        foreach ($urls as $url) {
            $unique[$url] = $url;
        }

        return array_values($unique);
    }
}
