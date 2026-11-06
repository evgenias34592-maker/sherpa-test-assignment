<?php

declare(strict_types=1);

namespace App\Messenger;

use Psr\Container\NotFoundExceptionInterface;

final class ServiceNotFound extends \RuntimeException implements NotFoundExceptionInterface
{
}
