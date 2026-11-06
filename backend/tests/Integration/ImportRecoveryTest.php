<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\ImportJob;
use App\Import\ImportFailureReporter;
use App\Import\UnfinishedImportRecoverer;
use App\Message\ImportProductsMessage;
use App\Tests\Support\TestKernel;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class ImportRecoveryTest extends TestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $container = TestKernel::container();
        TestKernel::reset();
        $em = $container->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $this->em = $em;
        $transport = $container->get(TransportInterface::class);
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        $transport->reset();
    }

    public function testRequeuesPendingAndProcessingJobs(): void
    {
        $container = TestKernel::container();
        $pending = new ImportJob('11111111-1111-4111-8111-111111111111', null, 'a.xlsx', 'imports/a.xlsx');
        $processing = new ImportJob('22222222-2222-4222-8222-222222222222', null, 'b.xlsx', 'imports/b.xlsx');
        $processing->begin();
        $done = new ImportJob('33333333-3333-4333-8333-333333333333', null, 'c.xlsx', 'imports/c.xlsx');
        $done->fail('Уже завершено с ошибкой');
        $this->em->persist($pending);
        $this->em->persist($processing);
        $this->em->persist($done);
        $this->em->flush();

        $recoverer = $container->get(UnfinishedImportRecoverer::class);
        self::assertInstanceOf(UnfinishedImportRecoverer::class, $recoverer);
        self::assertSame(2, $recoverer->requeue());

        $transport = $container->get(TransportInterface::class);
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        $ids = [];
        foreach ($transport->getSent() as $envelope) {
            $message = $envelope->getMessage();
            self::assertInstanceOf(ImportProductsMessage::class, $message);
            $ids[] = $message->jobId;
        }
        self::assertSame([
            '11111111-1111-4111-8111-111111111111',
            '22222222-2222-4222-8222-222222222222',
        ], $ids);
    }

    public function testInterruptedImportIsMarkedFailed(): void
    {
        $container = TestKernel::container();
        $job = new ImportJob('44444444-4444-4444-8444-444444444444', null, 'd.xlsx', 'imports/d.xlsx');
        $job->begin();
        $this->em->persist($job);
        $this->em->flush();

        $reporter = $container->get(ImportFailureReporter::class);
        self::assertInstanceOf(ImportFailureReporter::class, $reporter);
        $reporter->markFailed($job->id(), new \RuntimeException('boom'));

        $this->em->clear();
        $fresh = $this->em->find(ImportJob::class, $job->id());
        self::assertInstanceOf(ImportJob::class, $fresh);
        self::assertSame(ImportJob::FAILED, $fresh->status());
        self::assertSame('Импорт прерван', $fresh->message());
    }
}
