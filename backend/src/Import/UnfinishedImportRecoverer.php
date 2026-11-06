<?php

declare(strict_types=1);

namespace App\Import;

use App\Message\ImportProductsMessage;
use App\Repository\ImportJobRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class UnfinishedImportRecoverer
{
    public function __construct(
        private readonly ImportJobRepository $jobs,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function requeue(): int
    {
        $count = 0;
        foreach ($this->jobs->findUnfinished() as $job) {
            $this->bus->dispatch(new ImportProductsMessage($job->id()));
            ++$count;
        }
        if ($count > 0) {
            $this->logger->info('Requeued unfinished imports', ['count' => $count]);
        }

        return $count;
    }
}
