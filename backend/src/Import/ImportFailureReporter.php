<?php

declare(strict_types=1);

namespace App\Import;

use App\Entity\ImportJob;
use App\Infrastructure\DoctrineFactory;
use App\Settings;
use Psr\Log\LoggerInterface;

final class ImportFailureReporter
{
    public function __construct(
        private readonly Settings $settings,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function markFailed(string $jobId, \Throwable $exception): void
    {
        $this->logger->error('Import interrupted', ['job' => $jobId, 'exception' => $exception]);
        try {
            $em = DoctrineFactory::create($this->settings);
            $connection = $em->getConnection();
            try {
                $job = $em->find(ImportJob::class, $jobId);
                if (!$job instanceof ImportJob) {
                    return;
                }
                if ($job->status() === ImportJob::COMPLETED || $job->status() === ImportJob::FAILED) {
                    return;
                }
                $job->fail('Импорт прерван');
                $em->flush();
            } finally {
                $em->close();
                $connection->close();
            }
        } catch (\Throwable $reportException) {
            $this->logger->error('Could not mark import as failed', ['job' => $jobId, 'exception' => $reportException]);
        }
    }
}
