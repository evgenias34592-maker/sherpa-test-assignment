<?php

declare(strict_types=1);

namespace App;

final class Settings
{
    public function __construct(
        public readonly string $environment,
        public readonly bool $debug,
        public readonly string $databaseUrl,
        public readonly string $jwtSecret,
        public readonly int $jwtTtl,
        public readonly string $storagePath,
        public readonly int $importMaxBytes,
        public readonly int $importRateLimit,
        public readonly int $importRateWindow,
        public readonly int $loginRateLimit,
        public readonly int $imageMaxBytes,
        public readonly int $imageTimeout,
        public readonly string $rabbitDsn,
        public readonly string $messengerTransport,
        public readonly string $adminEmail,
        public readonly string $adminPassword,
        public readonly string $adminName,
        public readonly string $corsOrigin,
        public readonly string $projectDir,
    ) {
    }

    public static function fromEnvironment(string $projectDir): self
    {
        $environment = self::env('APP_ENV', 'prod');
        $secret = self::env('JWT_SECRET');
        if (strlen($secret) < 32) {
            throw new \RuntimeException('JWT_SECRET must be at least 32 characters');
        }

        $databaseUrl = self::env('DATABASE_URL');
        $rabbitDsn = self::env('RABBITMQ_DSN', 'amqp://products:products@127.0.0.1:5672/%2f');
        $adminPassword = self::env('ADMIN_PASSWORD', 'admin12345');
        if ($environment === 'prod') {
            self::rejectProductionDefaults($secret, $adminPassword, $databaseUrl, $rabbitDsn);
        }

        $storage = self::env('STORAGE_PATH', $projectDir . '/var/storage');

        return new self(
            environment: $environment,
            debug: self::env('APP_DEBUG', '0') === '1',
            databaseUrl: $databaseUrl,
            jwtSecret: $secret,
            jwtTtl: self::intEnv('JWT_TTL', 28800),
            storagePath: $storage,
            importMaxBytes: self::intEnv('IMPORT_MAX_BYTES', 10_485_760),
            importRateLimit: self::intEnv('IMPORT_RATE_LIMIT', 5),
            importRateWindow: self::intEnv('IMPORT_RATE_WINDOW', 60),
            loginRateLimit: self::intEnv('LOGIN_RATE_LIMIT', 10),
            imageMaxBytes: self::intEnv('IMAGE_MAX_BYTES', 5_242_880),
            imageTimeout: self::intEnv('IMAGE_TIMEOUT', 12),
            rabbitDsn: $rabbitDsn,
            messengerTransport: self::env('MESSENGER_TRANSPORT', 'amqp'),
            adminEmail: strtolower(self::env('ADMIN_EMAIL', 'admin@example.com')),
            adminPassword: $adminPassword,
            adminName: self::env('ADMIN_NAME', 'Administrator'),
            corsOrigin: self::env('CORS_ORIGIN', 'http://localhost:4200'),
            projectDir: $projectDir,
        );
    }

    private static function rejectProductionDefaults(
        string $secret,
        string $adminPassword,
        string $databaseUrl,
        string $rabbitDsn,
    ): void {
        if ($secret === 'change-me-change-me-change-me-32') {
            throw new \RuntimeException('JWT_SECRET must be changed when APP_ENV=prod');
        }

        $passwords = [
            'ADMIN_PASSWORD' => $adminPassword,
            'DATABASE_URL' => self::urlPassword($databaseUrl),
            'RABBITMQ_DSN' => self::urlPassword($rabbitDsn),
            'DB_PASSWORD' => self::configured('DB_PASSWORD') ?? '',
            'RABBITMQ_PASSWORD' => self::configured('RABBITMQ_PASSWORD') ?? '',
        ];
        foreach ($passwords as $name => $password) {
            if ($password === 'admin12345' || $password === 'products') {
                throw new \RuntimeException($name . ' must be changed when APP_ENV=prod');
            }
        }
    }

    private static function urlPassword(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['pass']) || $parts['pass'] === '') {
            return '';
        }

        return rawurldecode($parts['pass']);
    }

    private static function env(string $key, ?string $default = null): string
    {
        $value = self::configured($key);
        if ($value === null) {
            if ($default === null) {
                throw new \RuntimeException('Missing environment variable ' . $key);
            }

            return $default;
        }

        return $value;
    }

    private static function configured(string $key): ?string
    {
        $value = self::stringEnv($_ENV[$key] ?? null);
        if ($value === null) {
            $value = self::stringEnv($_SERVER[$key] ?? null);
        }
        if ($value === null) {
            $fromProcess = getenv($key);
            $value = self::stringEnv($fromProcess === false ? null : $fromProcess);
        }

        return $value;
    }

    private static function stringEnv(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    private static function intEnv(string $key, int $default): int
    {
        $raw = self::env($key, (string) $default);
        if (!preg_match('/^-?\d+$/', $raw)) {
            throw new \RuntimeException('Environment variable ' . $key . ' must be an integer');
        }

        return (int) $raw;
    }
}
