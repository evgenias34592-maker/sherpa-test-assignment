<?php

declare(strict_types=1);

namespace App\Infrastructure;

use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\MigratorConfiguration;
use Doctrine\ORM\EntityManagerInterface;

final class Migrator
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly string $projectDir)
    {
    }

    public function migrate(): void
    {
        $factory = DependencyFactory::fromConnection(
            new ConfigurationArray([
                'migrations_paths' => [
                    'App\\Migrations' => $this->projectDir . '/migrations',
                ],
                'table_storage' => [
                    'table_name' => 'doctrine_migration_versions',
                ],
            ]),
            new ExistingConnection($this->em->getConnection()),
        );
        $factory->getMetadataStorage()->ensureInitialized();
        $version = $factory->getVersionAliasResolver()->resolveVersionAlias('latest');
        $plan = $factory->getMigrationPlanCalculator()->getPlanUntilVersion($version);
        $factory->getMigrator()->migrate($plan, new MigratorConfiguration());
    }
}
