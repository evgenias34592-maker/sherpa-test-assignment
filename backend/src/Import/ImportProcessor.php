<?php

declare(strict_types=1);

namespace App\Import;

use App\Entity\ImportJob;
use App\Repository\ImportJobRepository;
use App\Storage\Storage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class ImportProcessor
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ImportJobRepository $jobs,
        private readonly SpreadsheetParser $parser,
        private readonly ProductImporter $importer,
        private readonly Storage $storage,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function process(string $jobId): void
    {
        $job = $this->jobs->find($jobId);
        if ($job === null || $job->status() === ImportJob::COMPLETED || $job->status() === ImportJob::FAILED) {
            return;
        }

        $job->begin();
        $this->em->flush();

        try {
            $rows = $this->parser->parse($this->storage->absolute($job->storedPath()));
        } catch (\Throwable $exception) {
            $this->logger->error('Import file rejected', ['job' => $jobId, 'exception' => $exception]);
            $this->em->clear();
            $failed = $this->jobs->mustFind($jobId);
            $failed->fail($exception instanceof SpreadsheetStructureException
                ? $exception->getMessage()
                : 'Не удалось прочитать файл');
            $this->em->flush();

            return;
        }

        $this->em->clear();
        $job = $this->jobs->mustFind($jobId);
        $job->setTotalRows(count($rows));
        $this->em->flush();

        foreach ($rows as $row) {
            try {
                $result = $this->importer->import($row);
                $this->em->clear();
                $job = $this->jobs->mustFind($jobId);
                if ($result->created) {
                    $job->incrementCreated();
                } else {
                    $job->incrementUpdated();
                }
                foreach ($result->issues as $issue) {
                    $job->addIssue($issue['row'], $issue['externalCode'], $issue['message'], $issue['level']);
                }
            } catch (RowImportException $exception) {
                $this->logger->info('Import row skipped', ['job' => $jobId, 'row' => $row->number, 'reason' => $exception->getMessage()]);
                $this->em->clear();
                $job = $this->jobs->mustFind($jobId);
                $code = $row->externalCode !== '' ? $row->externalCode : null;
                $job->addIssue($row->number, $code, $exception->getMessage(), 'error');
            }
            $job->incrementProcessed();
            $this->em->flush();
        }

        $this->em->clear();
        $job = $this->jobs->mustFind($jobId);
        $job->finish();
        $this->em->flush();
    }
}
