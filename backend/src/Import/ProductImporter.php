<?php

declare(strict_types=1);

namespace App\Import;

use App\Entity\Product;
use App\Entity\ProductAttribute;
use App\Entity\ProductImage;
use App\Repository\ProductRepository;
use App\Storage\Storage;
use Doctrine\ORM\EntityManagerInterface;

final class ProductImporter
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProductRepository $products,
        private readonly MoneyParser $money,
        private readonly DiscountCalculator $discounts,
        private readonly ImageFetcher $images,
        private readonly Storage $storage,
    ) {
    }

    public function import(ParsedRow $row): UpsertResult
    {
        if ($row->rejection !== null) {
            throw new RowImportException($row->rejection);
        }
        $externalCode = $this->externalCode($row->externalCode);
        $name = $this->bounded($row->name, 500, 'Наименование');
        if ($name === '') {
            throw new RowImportException('Пустое наименование');
        }
        $description = $this->bounded($row->description, 20000, 'Описание');
        $sale = $this->money->parse($row->priceRaw);
        $purchase = $this->money->parse($row->purchaseRaw);
        $discount = $this->discounts->percent($sale, $purchase);
        $attributes = $this->attributes($row->attributes);
        if (count($row->imageUrls) > 20) {
            throw new RowImportException('Слишком много изображений');
        }

        $stored = [];
        $issues = [];
        foreach ($row->imageUrls as $url) {
            try {
                $stored[] = $this->images->fetch($url);
            } catch (ImageFetchException $exception) {
                $issues[] = [
                    'row' => $row->number,
                    'externalCode' => $externalCode,
                    'message' => $exception->getMessage(),
                    'level' => 'warning',
                ];
            }
        }

        $created = false;
        /** @var list<string> $retired */
        $retired = [];
        try {
            $this->em->wrapInTransaction(function () use ($externalCode, $name, $description, $sale, $discount, $attributes, $stored, &$created, &$retired): void {
                $existing = $this->products->findByExternalCode($externalCode);
                if ($existing === null) {
                    $product = new Product($externalCode, $name, $description, $sale->format(), $discount);
                    $this->products->add($product);
                    $created = true;
                } else {
                    $product = $existing;
                    foreach ($product->images() as $image) {
                        $retired[] = $image->path();
                    }
                    $product->replace($name, $description, $sale->format(), $discount);
                    $this->products->removeAttributesAndImages($product);
                    $created = false;
                }
                foreach ($attributes as $attribute) {
                    $product->addAttribute(new ProductAttribute($attribute['key'], $attribute['value']));
                }
                foreach ($stored as $index => $image) {
                    $product->addImage(new ProductImage($image->sourceUrl, $image->relativePath, $index));
                }
            });
        } catch (RowImportException $exception) {
            $this->discardNew($stored);
            throw $exception;
        } catch (\Throwable $exception) {
            $this->discardNew($stored);
            throw new RowImportException('Не удалось сохранить товар', 0, $exception);
        }

        $keep = [];
        foreach ($stored as $image) {
            $keep[$image->relativePath] = true;
        }
        foreach ($retired as $path) {
            if (!isset($keep[$path])) {
                $this->storage->delete($path);
            }
        }

        return new UpsertResult($created, $issues);
    }

    private function externalCode(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new RowImportException('Пустой внешний код');
        }
        if (strlen($value) > 128 || preg_match('/[\x00-\x1F]/', $value) === 1) {
            throw new RowImportException('Некорректный внешний код');
        }

        return $value;
    }

    private function bounded(string $value, int $max, string $label): string
    {
        $value = trim($value);
        if (mb_strlen($value) > $max) {
            throw new RowImportException($label . ' слишком длинное');
        }

        return $value;
    }

    /**
     * @param list<array{key: string, value: string}> $attributes
     * @return list<array{key: string, value: string}>
     */
    private function attributes(array $attributes): array
    {
        if (count($attributes) > 100) {
            throw new RowImportException('Слишком много дополнительных полей');
        }
        $clean = [];
        foreach ($attributes as $attribute) {
            $key = trim($attribute['key']);
            $value = trim($attribute['value']);
            if ($key === '' || $value === '') {
                continue;
            }
            if (mb_strlen($key) > 255 || mb_strlen($value) > 5000) {
                throw new RowImportException('Дополнительное поле превышает допустимую длину');
            }
            $clean[$key] = ['key' => $key, 'value' => $value];
        }

        return array_values($clean);
    }

    /** @param list<StoredImage> $stored */
    private function discardNew(array $stored): void
    {
        foreach ($stored as $image) {
            $this->images->discard($image);
        }
    }
}
