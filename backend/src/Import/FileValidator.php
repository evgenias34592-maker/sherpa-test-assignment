<?php

declare(strict_types=1);

namespace App\Import;

use App\Http\ApiException;

final class FileValidator
{
    public function __construct(
        private readonly int $maxBytes,
        private readonly int $maxUncompressedBytes = XlsxZipLimit::MAX_UNCOMPRESSED_BYTES,
    ) {
    }

    public function assertXlsx(string $path, string $clientName, int $size, ?string $clientMediaType): void
    {
        $extension = strtolower(pathinfo($clientName, PATHINFO_EXTENSION));
        if ($extension !== 'xlsx') {
            throw new ApiException('Допустим только файл с расширением .xlsx', 422);
        }
        if ($size <= 0) {
            throw new ApiException('Файл пустой', 422);
        }
        if ($size > $this->maxBytes) {
            throw new ApiException('Файл превышает допустимый размер', 413);
        }
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new ApiException('Не удалось прочитать файл', 422);
        }
        $magic = fread($handle, 4);
        fclose($handle);
        if (!is_string($magic) || !str_starts_with($magic, 'PK')) {
            throw new ApiException('Файл не является книгой xlsx', 422);
        }
        if (XlsxZipLimit::exceeds($path, $this->maxUncompressedBytes)) {
            throw new ApiException('Архив xlsx превышает допустимый размер в распакованном виде', 413);
        }

        $zip = new \ZipArchive();
        $opened = $zip->open($path);
        if ($opened !== true) {
            throw new ApiException('Не удалось открыть xlsx', 422);
        }
        $hasWorkbook = $zip->locateName('xl/workbook.xml') !== false;
        $zip->close();
        if (!$hasWorkbook) {
            throw new ApiException('В файле нет книги Excel', 422);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->file($path);
        $allowed = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
            'application/x-zip-compressed',
            'application/octet-stream',
        ];
        if (!is_string($detected) || !in_array($detected, $allowed, true)) {
            throw new ApiException('Недопустимый тип файла', 422);
        }
        if ($clientMediaType !== null && $clientMediaType !== '') {
            $clientType = strtolower(trim(explode(';', $clientMediaType)[0]));
            if (!in_array($clientType, $allowed, true)) {
                throw new ApiException('Недопустимый MIME-тип файла', 422);
            }
        }
    }
}
