<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Bootstrap;
use App\Import\ImageFetcher;
use App\Infrastructure\Migrator;
use App\Storage\Storage;
use DI\Container;
use Doctrine\ORM\EntityManagerInterface;

final class TestKernel
{
    private static ?Container $container = null;

    public static function container(): Container
    {
        if (self::$container instanceof Container) {
            return self::$container;
        }

        $storage = sys_get_temp_dir() . '/products-import-test';
        $env = [
            'APP_ENV' => 'test',
            'APP_DEBUG' => '1',
            'DATABASE_URL' => getenv('DATABASE_URL') ?: 'postgresql://products:products@127.0.0.1:54329/products_test',
            'JWT_SECRET' => 'test-secret-test-secret-test-secret',
            'JWT_TTL' => '3600',
            'STORAGE_PATH' => $storage,
            'IMPORT_MAX_BYTES' => '10485760',
            'IMPORT_RATE_LIMIT' => '5',
            'IMPORT_RATE_WINDOW' => '60',
            'LOGIN_RATE_LIMIT' => '20',
            'IMAGE_MAX_BYTES' => '1048576',
            'IMAGE_TIMEOUT' => '3',
            'RABBITMQ_DSN' => 'amqp://products:products@127.0.0.1:56729/%2f',
            'MESSENGER_TRANSPORT' => 'in-memory',
            'ADMIN_EMAIL' => 'admin@example.com',
            'ADMIN_PASSWORD' => 'admin12345',
            'ADMIN_NAME' => 'Administrator',
            'CORS_ORIGIN' => 'http://localhost:4200',
        ];
        foreach ($env as $key => $value) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        $container = Bootstrap::container();
        if (!$container instanceof Container) {
            throw new \RuntimeException('Expected PHP-DI container');
        }
        $container->set(ImageFetcher::class, new FakeImageFetcher($container->get(Storage::class)));
        $container->get(Migrator::class)->migrate();
        self::$container = $container;

        return $container;
    }

    public static function reset(): void
    {
        $container = self::container();
        $connection = $container->get(EntityManagerInterface::class)->getConnection();
        $connection->executeStatement(
            'TRUNCATE TABLE rate_limit_hits, import_jobs, product_images, product_attributes, products, users RESTART IDENTITY CASCADE',
        );
        $container->get(EntityManagerInterface::class)->clear();
    }
}
