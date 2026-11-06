<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\ImportJob;
use PHPUnit\Framework\TestCase;

final class ImportJobTest extends TestCase
{
    public function testIssueListStopsAtFiveHundredWhileCountersContinue(): void
    {
        $job = new ImportJob('job-1', 1, 'import.xlsx', 'imports/job-1.xlsx');
        for ($row = 1; $row <= 501; ++$row) {
            $job->addIssue($row, 'code', 'Ошибка', 'error');
        }
        $job->addIssue(502, 'code', 'Картинка не скачалась', 'warning');

        self::assertCount(500, $job->errors());
        self::assertSame(1, $job->errors()[0]['row']);
        self::assertSame(500, $job->errors()[499]['row']);
        self::assertSame(501, $job->errorCount());
        self::assertSame(1, $job->warningCount());
    }
}
