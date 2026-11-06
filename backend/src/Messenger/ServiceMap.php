<?php

declare(strict_types=1);

namespace App\Messenger;

use Psr\Container\ContainerInterface;

final class ServiceMap implements ContainerInterface
{
    /** @param array<string, object> $services */
    public function __construct(private readonly array $services)
    {
    }

    public function get(string $id): object
    {
        if (!$this->has($id)) {
            throw new ServiceNotFound('Service not found: ' . $id);
        }

        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }
}
