<?php

declare(strict_types=1);

namespace App\Message;

final class ImportProductsMessage
{
    public function __construct(public readonly string $jobId)
    {
    }
}
