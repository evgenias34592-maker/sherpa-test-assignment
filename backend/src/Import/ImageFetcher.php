<?php

declare(strict_types=1);

namespace App\Import;

interface ImageFetcher
{
    public function fetch(string $url): StoredImage;

    public function discard(StoredImage $image): void;
}
