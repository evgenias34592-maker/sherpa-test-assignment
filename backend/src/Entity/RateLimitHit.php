<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'rate_limit_hits')]
#[ORM\Index(name: 'rate_limit_hits_bucket_idx', columns: ['bucket', 'hit_at'])]
class RateLimitHit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 190)]
    private string $bucket;

    #[ORM\Column(name: 'hit_at', type: 'integer')]
    private int $hitAt;

    public function __construct(string $bucket, int $hitAt, ?int $id = null)
    {
        $this->id = $id;
        $this->bucket = $bucket;
        $this->hitAt = $hitAt;
    }

    public function id(): int
    {
        if ($this->id === null) {
            throw new \LogicException('Hit is not persisted');
        }

        return $this->id;
    }

    public function bucket(): string
    {
        return $this->bucket;
    }

    public function hitAt(): int
    {
        return $this->hitAt;
    }
}
