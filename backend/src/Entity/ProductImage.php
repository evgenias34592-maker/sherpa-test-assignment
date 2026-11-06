<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'product_images')]
class ProductImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'images')]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Product $product = null;

    #[ORM\Column(type: 'text')]
    private string $url;

    #[ORM\Column(type: 'string', length: 500)]
    private string $path;

    #[ORM\Column(name: 'sort_order', type: 'integer')]
    private int $sortOrder;

    public function __construct(string $url, string $path, int $sortOrder, ?int $id = null)
    {
        $this->id = $id;
        $this->url = $url;
        $this->path = $path;
        $this->sortOrder = $sortOrder;
    }

    public function id(): int
    {
        if ($this->id === null) {
            throw new \LogicException('Image is not persisted');
        }

        return $this->id;
    }

    public function product(): Product
    {
        if (!$this->product instanceof Product) {
            throw new \LogicException('Image is not attached');
        }

        return $this->product;
    }

    public function attach(Product $product): void
    {
        $this->product = $product;
    }

    public function url(): string
    {
        return $this->url;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function sortOrder(): int
    {
        return $this->sortOrder;
    }
}
