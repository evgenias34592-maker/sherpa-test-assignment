<?php

declare(strict_types=1);

namespace App\Message;

use Symfony\Component\Messenger\Stamp\StampInterface;

final class DeliveryTagStamp implements StampInterface
{
    public function __construct(public readonly int $tag)
    {
    }
}
