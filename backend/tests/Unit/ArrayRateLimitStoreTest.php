<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Security\ArrayRateLimitStore;
use PHPUnit\Framework\TestCase;

final class ArrayRateLimitStoreTest extends TestCase
{
    public function testBlocksTheRequestAfterTheLimit(): void
    {
        $store = new ArrayRateLimitStore();
        self::assertTrue($store->hit('import:user:1', 1_000, 60, 2)->allowed);
        self::assertTrue($store->hit('import:user:1', 1_010, 60, 2)->allowed);
        $blocked = $store->hit('import:user:1', 1_020, 60, 2);

        self::assertFalse($blocked->allowed);
        self::assertSame(40, $blocked->retryAfter);
    }
}
