<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Import\ImageFetcher;
use App\Import\StoredImage;
use App\Storage\Storage;

final class FakeImageFetcher implements ImageFetcher
{
    public function __construct(private readonly Storage $storage)
    {
    }

    public function fetch(string $url): StoredImage
    {
        $relative = 'images/fake/' . hash('sha256', $url) . '.png';
        $path = $this->storage->absolute($relative);
        if (!is_file($path)) {
            $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);
            file_put_contents($path, $png === false ? '' : $png);
        }

        return new StoredImage($url, $relative, false);
    }

    public function discard(StoredImage $image): void
    {
        if ($image->created) {
            $this->storage->delete($image->relativePath);
        }
    }
}
