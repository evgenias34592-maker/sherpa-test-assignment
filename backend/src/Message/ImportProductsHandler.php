<?php

declare(strict_types=1);

namespace App\Message;

use App\Import\ImportFailureReporter;
use App\Import\ImportProcessor;

final class ImportProductsHandler
{
    public function __construct(
        private readonly ImportProcessor $processor,
        private readonly ImportFailureReporter $failures,
    ) {
    }

    public function __invoke(ImportProductsMessage $message): void
    {
        try {
            $this->processor->process($message->jobId);
        } catch (\Throwable $exception) {
            $this->failures->markFailed($message->jobId, $exception);
            throw $exception;
        }
    }
}
