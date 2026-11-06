<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Settings;
use PHPUnit\Framework\TestCase;

final class SettingsTest extends TestCase
{
    public function testDevKeepsClassroomCredentials(): void
    {
        $this->withEnv([
            'APP_ENV' => 'dev',
            'JWT_SECRET' => 'change-me-change-me-change-me-32',
            'DATABASE_URL' => 'postgresql://products:products@db:5432/products',
            'RABBITMQ_DSN' => 'amqp://products:products@rabbitmq:5672/%2f',
            'ADMIN_PASSWORD' => 'admin12345',
            'DB_PASSWORD' => 'products',
            'RABBITMQ_PASSWORD' => 'products',
        ], function (): void {
            $settings = Settings::fromEnvironment(dirname(__DIR__, 2));

            self::assertSame('dev', $settings->environment);
            self::assertSame('admin12345', $settings->adminPassword);
            self::assertSame('change-me-change-me-change-me-32', $settings->jwtSecret);
        });
    }

    public function testProdAcceptsReplacedCredentials(): void
    {
        $this->withEnv($this->productionEnv(), function (): void {
            $settings = Settings::fromEnvironment(dirname(__DIR__, 2));

            self::assertSame('prod', $settings->environment);
            self::assertSame('not-the-classroom-password', $settings->adminPassword);
        });
    }

    public function testProdRejectsPlaceholderSecret(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JWT_SECRET');
        $this->withEnv($this->productionEnv([
            'JWT_SECRET' => 'change-me-change-me-change-me-32',
        ]), function (): void {
            Settings::fromEnvironment(dirname(__DIR__, 2));
        });
    }

    public function testProdRejectsClassroomPasswords(): void
    {
        $cases = [
            'ADMIN_PASSWORD' => ['ADMIN_PASSWORD' => 'admin12345'],
            'ADMIN_PASSWORD products' => ['ADMIN_PASSWORD' => 'products'],
            'DATABASE_URL' => ['DATABASE_URL' => 'postgresql://products:products@db:5432/products'],
            'RABBITMQ_DSN' => ['RABBITMQ_DSN' => 'amqp://products:products@rabbitmq:5672/%2f'],
            'DB_PASSWORD' => ['DB_PASSWORD' => 'products'],
            'RABBITMQ_PASSWORD' => ['RABBITMQ_PASSWORD' => 'admin12345'],
        ];
        foreach ($cases as $label => $override) {
            try {
                $this->withEnv($this->productionEnv($override), function (): void {
                    Settings::fromEnvironment(dirname(__DIR__, 2));
                });
                self::fail($label . ' must be rejected');
            } catch (\RuntimeException $exception) {
                self::assertStringContainsString('must be changed when APP_ENV=prod', $exception->getMessage(), $label);
            }
        }
    }

    /**
     * @param array<string, string> $override
     *
     * @return array<string, string>
     */
    private function productionEnv(array $override = []): array
    {
        return array_merge([
            'APP_ENV' => 'prod',
            'JWT_SECRET' => 'this-is-a-real-production-secret-32',
            'DATABASE_URL' => 'postgresql://products:not-a-default-password@db:5432/products',
            'RABBITMQ_DSN' => 'amqp://products:not-a-default-password@rabbitmq:5672/%2f',
            'ADMIN_PASSWORD' => 'not-the-classroom-password',
            'DB_PASSWORD' => 'not-a-default-password',
            'RABBITMQ_PASSWORD' => 'not-a-default-password',
        ], $override);
    }

    /**
     * @param array<string, string|null> $values
     */
    private function withEnv(array $values, callable $run): void
    {
        $saved = [];
        foreach (array_keys($values) as $key) {
            $saved[$key] = [
                'env' => $_ENV[$key] ?? null,
                'server' => $_SERVER[$key] ?? null,
                'hasEnv' => array_key_exists($key, $_ENV),
                'hasServer' => array_key_exists($key, $_SERVER),
                'process' => getenv($key),
            ];
        }
        try {
            foreach ($values as $key => $value) {
                if ($value === null) {
                    unset($_ENV[$key], $_SERVER[$key]);
                    putenv($key);
                    continue;
                }
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
                putenv($key . '=' . $value);
            }
            $run();
        } finally {
            foreach ($saved as $key => $previous) {
                if ($previous['hasEnv']) {
                    $_ENV[$key] = $previous['env'];
                } else {
                    unset($_ENV[$key]);
                }
                if ($previous['hasServer']) {
                    $_SERVER[$key] = $previous['server'];
                } else {
                    unset($_SERVER[$key]);
                }
                if ($previous['process'] === false) {
                    putenv($key);
                } else {
                    putenv($key . '=' . $previous['process']);
                }
            }
        }
    }
}
