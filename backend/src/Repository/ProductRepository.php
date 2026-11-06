<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use App\Entity\ProductImage;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

final class ProductRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function add(Product $product): void
    {
        $this->em->persist($product);
    }

    public function find(int $id): ?Product
    {
        $product = $this->em->find(Product::class, $id);

        return $product instanceof Product ? $product : null;
    }

    public function findByExternalCode(string $externalCode): ?Product
    {
        $product = $this->em->getRepository(Product::class)->findOneBy(['externalCode' => $externalCode]);

        return $product instanceof Product ? $product : null;
    }

    public function removeAttributesAndImages(Product $product): void
    {
        $this->em->createQuery('DELETE FROM App\Entity\ProductAttribute a WHERE a.product = :product')
            ->setParameter('product', $product)
            ->execute();
        $this->em->createQuery('DELETE FROM App\Entity\ProductImage i WHERE i.product = :product')
            ->setParameter('product', $product)
            ->execute();

        foreach ($product->attributes()->toArray() as $attribute) {
            $product->attributes()->removeElement($attribute);
            $this->em->detach($attribute);
        }
        foreach ($product->images()->toArray() as $image) {
            $product->images()->removeElement($image);
            $this->em->detach($image);
        }
    }

    /**
     * @return list<Product>
     */
    public function search(?string $name, ?string $priceMin, ?string $priceMax, int $page, int $limit): array
    {
        $query = $this->filtered($name, $priceMin, $priceMax)
            ->orderBy('p.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery();

        $items = [];
        foreach ($query->getResult() as $row) {
            if ($row instanceof Product) {
                $items[] = $row;
            }
        }

        return $items;
    }

    /**
     * First image path for each product, ordered as on the card.
     *
     * @param list<int> $productIds
     *
     * @return array<int, string>
     */
    public function coverPaths(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $rows = $this->em->createQueryBuilder()
            ->select('IDENTITY(i.product) AS productId', 'i.path AS path')
            ->from(ProductImage::class, 'i')
            ->where('i.product IN (:ids)')
            ->setParameter('ids', $productIds)
            ->orderBy('i.sortOrder', 'ASC')
            ->addOrderBy('i.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $covers = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = $row['productId'] ?? null;
            $path = $row['path'] ?? null;
            if (!is_numeric($id) || !is_string($path)) {
                continue;
            }
            $productId = (int) $id;
            if (!isset($covers[$productId])) {
                $covers[$productId] = $path;
            }
        }

        return $covers;
    }

    public function countSearch(?string $name, ?string $priceMin, ?string $priceMax): int
    {
        $count = $this->filtered($name, $priceMin, $priceMax)
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();

        return is_numeric($count) ? (int) $count : 0;
    }

    /**
     * @return list<Product>
     */
    public function export(?string $name, ?string $priceMin, ?string $priceMax, int $limit): array
    {
        return $this->search($name, $priceMin, $priceMax, 1, $limit);
    }

    private function filtered(?string $name, ?string $priceMin, ?string $priceMax): QueryBuilder
    {
        $qb = $this->em->createQueryBuilder()
            ->select('p')
            ->from(Product::class, 'p');

        if ($name !== null && $name !== '') {
            $qb->andWhere('LOWER(p.name) LIKE :name')
                ->setParameter('name', '%' . $this->escapeLike(mb_strtolower($name)) . '%');
        }
        if ($priceMin !== null) {
            $qb->andWhere('p.price >= :priceMin')->setParameter('priceMin', $priceMin);
        }
        if ($priceMax !== null) {
            $qb->andWhere('p.price <= :priceMax')->setParameter('priceMax', $priceMax);
        }

        return $qb;
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
