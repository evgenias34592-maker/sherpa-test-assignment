<?php

declare(strict_types=1);

namespace App\Http;

use App\Entity\ImportJob;

final class JobPresenter
{
    /** @return array<string, mixed> */
    public function present(ImportJob $job): array
    {
        return [
            'id' => $job->id(),
            'status' => $job->status(),
            'originalFilename' => $job->originalFilename(),
            'totalRows' => $job->totalRows(),
            'processedRows' => $job->processedRows(),
            'createdCount' => $job->createdCount(),
            'updatedCount' => $job->updatedCount(),
            'errorCount' => $job->errorCount(),
            'warningCount' => $job->warningCount(),
            'progress' => $job->progress(),
            'errors' => $job->errors(),
            'message' => $job->message(),
            'createdAt' => $job->createdAt()->format(\DateTimeInterface::ATOM),
            'startedAt' => $job->startedAt()?->format(\DateTimeInterface::ATOM),
            'finishedAt' => $job->finishedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
