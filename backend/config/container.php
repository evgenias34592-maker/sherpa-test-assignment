<?php

declare(strict_types=1);

use App\Import\HostResolver;
use App\Import\HttpImageFetcher;
use App\Import\ImageFetcher;
use App\Import\SystemHostResolver;
use App\Infrastructure\DoctrineFactory;
use App\Message\ImportProductsMessage;
use App\Messenger\PhpAmqpTransport;
use App\Messenger\ServiceMap;
use App\Security\DatabaseRateLimitStore;
use App\Security\RateLimitStore;
use App\Settings;
use App\Storage\Storage;

use function DI\autowire;
use function DI\factory;

use Doctrine\ORM\EntityManagerInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Transport\TransportInterface;

return [
    Settings::class => factory(static function (): Settings {
        return Settings::fromEnvironment(dirname(__DIR__));
    }),
    LoggerInterface::class => factory(static function (Settings $settings): LoggerInterface {
        $logger = new Logger('products');
        $logger->pushHandler(new StreamHandler('php://stderr', $settings->debug ? Level::Debug : Level::Info));

        return $logger;
    }),
    EntityManagerInterface::class => factory(static function (Settings $settings): EntityManagerInterface {
        return DoctrineFactory::create($settings);
    }),
    Storage::class => factory(static function (Settings $settings): Storage {
        return new Storage($settings->storagePath);
    }),
    HostResolver::class => autowire(SystemHostResolver::class),
    ImageFetcher::class => factory(static function (HttpImageFetcher $fetcher): ImageFetcher {
        return $fetcher;
    }),
    RateLimitStore::class => factory(static function (EntityManagerInterface $em): RateLimitStore {
        return new DatabaseRateLimitStore($em->getConnection());
    }),
    TransportInterface::class => factory(static function (Settings $settings): TransportInterface {
        if ($settings->messengerTransport === 'in-memory') {
            return new InMemoryTransport();
        }

        return new PhpAmqpTransport($settings->rabbitDsn);
    }),
    MessageBusInterface::class => factory(static function (TransportInterface $transport): MessageBusInterface {
        return new MessageBus([
            new SendMessageMiddleware(
                new SendersLocator([
                    ImportProductsMessage::class => ['async'],
                ], new ServiceMap(['async' => $transport])),
                new EventDispatcher(),
            ),
        ]);
    }),
    'messenger.worker_bus' => factory(static function (\App\Message\ImportProductsHandler $handler): MessageBusInterface {
        return new MessageBus([
            new HandleMessageMiddleware(new HandlersLocator([
                ImportProductsMessage::class => [$handler],
            ])),
        ]);
    }),
    HttpImageFetcher::class => factory(static function (Settings $settings, \App\Import\UrlSafetyChecker $urls, Storage $storage): HttpImageFetcher {
        return new HttpImageFetcher($urls, $storage, $settings->imageMaxBytes, $settings->imageTimeout);
    }),
    \App\Import\FileValidator::class => factory(static function (Settings $settings): \App\Import\FileValidator {
        return new \App\Import\FileValidator($settings->importMaxBytes);
    }),
    \App\Infrastructure\Migrator::class => factory(static function (Settings $settings, EntityManagerInterface $em): \App\Infrastructure\Migrator {
        return new \App\Infrastructure\Migrator($em, $settings->projectDir);
    }),
];
