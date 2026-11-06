<?php

declare(strict_types=1);

namespace App\Import;

final class XlsxZipLimit
{
    public const MAX_UNCOMPRESSED_BYTES = 64 * 1024 * 1024;

    /**
     * True when the central directory declares more uncompressed bytes than $limit.
     * The entries themselves are not inflated.
     */
    public static function exceeds(string $path, int $limit): bool
    {
        if ($limit < 0) {
            return true;
        }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return false;
        }
        try {
            $total = 0;
            $count = $zip->numFiles;
            for ($index = 0; $index < $count; ++$index) {
                $stat = $zip->statIndex($index);
                if (!is_array($stat)) {
                    return true;
                }
                $size = $stat['size'];
                if ($size < 0) {
                    return true;
                }
                if ($size > $limit - $total) {
                    return true;
                }
                $total += $size;
            }

            return false;
        } finally {
            $zip->close();
        }
    }
}
