<?php

declare(strict_types=1);

namespace App\Storage;

final class Storage
{
    public function __construct(private readonly string $root)
    {
        if (!is_dir($this->root) && !mkdir($this->root, 0775, true) && !is_dir($this->root)) {
            throw new \RuntimeException('Cannot create storage directory');
        }
        foreach (['imports', 'images'] as $directory) {
            $path = $this->root . '/' . $directory;
            if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
                throw new \RuntimeException('Cannot create storage directory');
            }
        }
    }

    public function root(): string
    {
        return $this->root;
    }

    public function absolute(string $relative): string
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        if ($relative === '' || str_contains($relative, "\0") || str_contains($relative, '..')) {
            throw new \RuntimeException('Invalid storage path');
        }
        $root = realpath($this->root);
        if ($root === false) {
            throw new \RuntimeException('Storage is not available');
        }
        $full = $root . '/' . $relative;
        $parent = dirname($full);
        if (!is_dir($parent) && !mkdir($parent, 0775, true) && !is_dir($parent)) {
            throw new \RuntimeException('Cannot create storage directory');
        }
        $realParent = realpath($parent);
        if ($realParent === false || !str_starts_with($realParent, $root)) {
            throw new \RuntimeException('Invalid storage path');
        }

        return $full;
    }

    public function publicUrl(string $relative): string
    {
        return '/media/' . ltrim(str_replace('\\', '/', $relative), '/');
    }

    public function delete(string $relative): void
    {
        $path = $this->absolute($relative);
        if (is_file($path)) {
            unlink($path);
        }
    }
}
