<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Product;
use App\Entity\ProductAttribute;
use App\Entity\ProductImage;
use App\Repository\ProductRepository;
use App\Tests\Support\TestKernel;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class CatalogCrudTest extends TestCase
{
    private EntityManagerInterface $em;

    private ProductRepository $products;

    protected function setUp(): void
    {
        $container = TestKernel::container();
        TestKernel::reset();
        $em = $container->get(EntityManagerInterface::class);
        $products = $container->get(ProductRepository::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        self::assertInstanceOf(ProductRepository::class, $products);
        $this->em = $em;
        $this->products = $products;
    }

    public function testCreatesReadsUpdatesAndDeletesAProductWithChildren(): void
    {
        $product = new Product('crud-1', 'Первый товар', 'Описание', '100.00', '10.00');
        $product->addAttribute(new ProductAttribute('Цвет', 'Nero'));
        $product->addImage(new ProductImage('https://example.com/a.jpg', 'images/fake/a.png', 0));
        $this->products->add($product);
        $this->em->flush();
        $id = $product->id();
        $this->em->clear();

        $loaded = $this->products->find($id);
        self::assertNotNull($loaded);
        self::assertSame('Первый товар', $loaded->name());
        self::assertCount(1, $loaded->attributes());
        self::assertCount(1, $loaded->images());

        $loaded->replace('Обновлённый товар', 'Новое описание', '120.00', '15.00');
        $this->products->removeAttributesAndImages($loaded);
        $loaded->addAttribute(new ProductAttribute('Бренд', 'MINIMI'));
        $this->em->flush();
        $this->em->clear();

        $updated = $this->products->find($id);
        self::assertNotNull($updated);
        self::assertSame('120.00', $updated->price());
        $keys = [];
        foreach ($updated->attributes() as $attribute) {
            $keys[] = $attribute->key();
        }
        self::assertSame(['Бренд'], $keys);

        $found = $this->products->search('обнов', '110.00', '130.00', 1, 20);
        self::assertCount(1, $found);
        self::assertSame(0, $this->products->countSearch('обнов', '200.00', '300.00'));

        $this->em->remove($updated);
        $this->em->flush();
        $this->em->clear();

        self::assertNull($this->products->find($id));
        self::assertNull($this->products->findByExternalCode('crud-1'));
    }

    public function testCoverPathsUsesTheFirstImageOfEachProduct(): void
    {
        $first = new Product('cover-1', 'Один', 'Длинное описание', '10.00', '0.00');
        $first->addImage(new ProductImage('https://example.com/b.jpg', 'images/fake/b.png', 1));
        $first->addImage(new ProductImage('https://example.com/a.jpg', 'images/fake/a.png', 0));
        $second = new Product('cover-2', 'Два', 'Ещё описание', '20.00', '0.00');
        $second->addImage(new ProductImage('https://example.com/c.jpg', 'images/fake/c.png', 3));
        $bare = new Product('cover-3', 'Без фото', 'Описание', '30.00', '0.00');
        $this->products->add($first);
        $this->products->add($second);
        $this->products->add($bare);
        $this->em->flush();
        $ids = [$first->id(), $second->id(), $bare->id()];
        $this->em->clear();

        self::assertSame([], $this->products->coverPaths([]));
        $covers = $this->products->coverPaths($ids);
        self::assertSame([
            $ids[0] => 'images/fake/a.png',
            $ids[1] => 'images/fake/c.png',
        ], $covers);
    }
}
