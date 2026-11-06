<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'products')]
#[ORM\UniqueConstraint(name: 'products_external_code_unique', columns: ['external_code'])]
#[ORM\Index(name: 'products_price_idx', columns: ['price'])]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'external_code', type: 'string', length: 128)]
    private string $externalCode;

    #[ORM\Column(type: 'string', length: 500)]
    private string $name;

    #[ORM\Column(type: 'text')]
    private string $description;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $price;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 2)]
    private string $discount;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, ProductAttribute> */
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductAttribute::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $attributes;

    /** @var Collection<int, ProductImage> */
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductImage::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC', 'id' => 'ASC'])]
    private Collection $images;

    public function __construct(
        string $externalCode,
        string $name,
        string $description,
        string $price,
        string $discount,
        ?int $id = null,
    ) {
        $this->id = $id;
        $this->externalCode = $externalCode;
        $this->name = $name;
        $this->description = $description;
        $this->price = $price;
        $this->discount = $discount;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->attributes = new ArrayCollection();
        $this->images = new ArrayCollection();
    }

    public function id(): int
    {
        if ($this->id === null) {
            throw new \LogicException('Product is not persisted');
        }

        return $this->id;
    }

    public function externalCode(): string
    {
        return $this->externalCode;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function price(): string
    {
        return $this->price;
    }

    public function discount(): string
    {
        return $this->discount;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function replace(string $name, string $description, string $price, string $discount): void
    {
        $this->name = $name;
        $this->description = $description;
        $this->price = $price;
        $this->discount = $discount;
        $this->updatedAt = new \DateTimeImmutable();
    }

    /** @return Collection<int, ProductAttribute> */
    public function attributes(): Collection
    {
        return $this->attributes;
    }

    /** @return Collection<int, ProductImage> */
    public function images(): Collection
    {
        return $this->images;
    }

    public function addAttribute(ProductAttribute $attribute): void
    {
        $this->attributes->add($attribute);
        $attribute->attach($this);
    }

    public function addImage(ProductImage $image): void
    {
        $this->images->add($image);
        $image->attach($this);
    }
}
