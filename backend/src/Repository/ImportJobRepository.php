<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ImportJob;
use Doctrine\ORM\EntityManagerInterface;

final class ImportJobRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function add(ImportJob $job): void
    {
        $this->em->persist($job);
    }

    public function find(string $id): ?ImportJob
    {
        $job = $this->em->find(ImportJob::class, $id);

        return $job instanceof ImportJob ? $job : null;
    }

    public function mustFind(string $id): ImportJob
    {
        $job = $this->find($id);
        if ($job === null) {
            throw new \RuntimeException('Import job not found');
        }

        return $job;
    }

    /** @return list<ImportJob> */
    public function findUnfinished(): array
    {
        $result = $this->em->createQueryBuilder()
            ->select('job')
            ->from(ImportJob::class, 'job')
            ->where('job.status IN (:statuses)')
            ->setParameter('statuses', [ImportJob::PENDING, ImportJob::PROCESSING])
            ->orderBy('job.createdAt', 'ASC')
            ->addOrderBy('job.id', 'ASC')
            ->getQuery()
            ->getResult();

        $jobs = [];
        foreach ($result as $job) {
            if (!$job instanceof ImportJob) {
                throw new \RuntimeException('Unexpected import job result');
            }
            $jobs[] = $job;
        }

        return $jobs;
    }
}
