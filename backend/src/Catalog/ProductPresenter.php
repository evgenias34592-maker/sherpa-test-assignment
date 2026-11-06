<?php

declare(strict_types=1);

namespace App\Catalog;

use App\Entity\Product;
use App\Entity\ProductImage;
use App\Storage\Storage;

final class ProductPresenter
{
    public function __construct(private readonly Storage $storage)
    {
    }

    /** @return array<string, mixed> */
    public function summary(Product $product, ?string $coverPath): array
    {
        return [
            'id' => $product->id(),
            'externalCode' => $product->externalCode(),
            'name' => $product->name(),
            'price' => $this->money($product->price()),
            'discount' => $this->money($product->discount()),
            'updatedAt' => $product->updatedAt()->format(DATE_ATOM),
            'cover' => $coverPath === null ? null : $this->storage->publicUrl($coverPath),
        ];
    }

    /** @return array<string, mixed> */
    public function detail(Product $product): array
    {
        $first = $product->images()->first();
        $data = $this->summary($product, $first instanceof ProductImage ? $first->path() : null);
        $data['description'] = $product->description();
        unset($data['cover']);
        $attributes = [];
        foreach ($product->attributes() as $attribute) {
            $attributes[] = [
                'key' => $attribute->key(),
                'value' => $attribute->value(),
            ];
        }
        $images = [];
        foreach ($product->images() as $image) {
            $images[] = [
                'url' => $image->url(),
                'path' => $image->path(),
                'publicUrl' => $this->storage->publicUrl($image->path()),
                'sortOrder' => $image->sortOrder(),
            ];
        }
        $data['attributes'] = $attributes;
        $data['images'] = $images;

        return $data;
    }

    private function money(string $value): string
    {
        if (!str_contains($value, '.')) {
            return $value . '.00';
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '0');

        return $whole . '.' . str_pad(substr($fraction, 0, 2), 2, '0');
    }
}
