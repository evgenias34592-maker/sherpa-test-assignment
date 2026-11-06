<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'product_attributes')]
#[ORM\UniqueConstraint(name: 'product_attributes_product_key_unique', columns: ['product_id', 'key'])]
class ProductAttribute
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'attributes')]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Product $product = null;

    #[ORM\Column(name: 'key', type: 'string', length: 255)]
    private string $key;

    #[ORM\Column(name: 'value', type: 'text')]
    private string $value;

    public function __construct(string $key, string $value, ?int $id = null)
    {
        $this->id = $id;
        $this->key = $key;
        $this->value = $value;
    }

    public function id(): int
    {
        if ($this->id === null) {
            throw new \LogicException('Attribute is not persisted');
        }

        return $this->id;
    }

    public function product(): Product
    {
        if (!$this->product instanceof Product) {
            throw new \LogicException('Attribute is not attached');
        }

        return $this->product;
    }

    public function attach(Product $product): void
    {
        $this->product = $product;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function value(): string
    {
        return $this->value;
    }
}
