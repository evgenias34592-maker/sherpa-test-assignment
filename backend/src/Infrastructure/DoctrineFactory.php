<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Settings;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

final class DoctrineFactory
{
    public static function create(Settings $settings): EntityManagerInterface
    {
        $proxyDir = $settings->projectDir . '/var/proxies';
        if (!is_dir($proxyDir) && !mkdir($proxyDir, 0775, true) && !is_dir($proxyDir)) {
            throw new \RuntimeException('Cannot create proxy directory');
        }
        $cache = $settings->debug
            ? new ArrayAdapter()
            : new FilesystemAdapter('doctrine', 0, $settings->projectDir . '/var/cache');
        $config = ORMSetup::createAttributeMetadataConfiguration(
            [dirname(__DIR__) . '/Entity'],
            $settings->debug,
            $proxyDir,
            $cache,
        );
        $config->setProxyNamespace('App\Proxies');
        $connection = DriverManager::getConnection(self::params($settings->databaseUrl), $config);

        return new EntityManager($connection, $config);
    }

    /** @return array<string, mixed> */
    private static function params(string $url): array
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['host'], $parts['path'])) {
            throw new \RuntimeException('Invalid DATABASE_URL');
        }

        return [
            'driver' => 'pdo_pgsql',
            'host' => $parts['host'],
            'port' => $parts['port'] ?? 5432,
            'dbname' => ltrim((string) $parts['path'], '/'),
            'user' => isset($parts['user']) ? rawurldecode((string) $parts['user']) : '',
            'password' => isset($parts['pass']) ? rawurldecode((string) $parts['pass']) : '',
        ];
    }
}
