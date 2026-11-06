<?php

declare(strict_types=1);

namespace App\Import;

interface HostResolver
{
    /** @return list<string> */
    public function resolve(string $host): array;
}
