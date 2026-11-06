#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Import\UnfinishedImportRecoverer;
use App\Messenger\PhpAmqpTransport;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Messenger\Worker;

require dirname(__DIR__) . '/vendor/autoload.php';

$container = Bootstrap::container();
$logger = $container->get(LoggerInterface::class);
$transport = $container->get(TransportInterface::class);
$bus = $container->get('messenger.worker_bus');
if (!$bus instanceof MessageBusInterface) {
    throw new RuntimeException('Worker bus is not configured');
}

$recoverer = $container->get(UnfinishedImportRecoverer::class);
if (!$recoverer instanceof UnfinishedImportRecoverer) {
    throw new RuntimeException('Import recovery is not configured');
}
$recoverer->requeue();

$output = new ConsoleOutput();
$worker = new Worker(['async' => $transport], $bus, new EventDispatcher(), $logger);
if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    $stop = static function () use ($worker, $output): void {
        $output->writeln('Stopping worker');
        $worker->stop();
    };
    pcntl_signal(SIGTERM, $stop);
    pcntl_signal(SIGINT, $stop);
}

try {
    $worker->run(['sleep' => 500000]);
} finally {
    if ($transport instanceof PhpAmqpTransport) {
        $transport->close();
    }
}
