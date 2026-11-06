<?php

declare(strict_types=1);

namespace App\Security;

use App\Settings;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class JwtService
{
    public function __construct(private readonly Settings $settings)
    {
    }

    public function issue(int $userId, string $email): string
    {
        $now = time();
        $payload = [
            'iss' => 'products-import',
            'sub' => (string) $userId,
            'email' => $email,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $this->settings->jwtTtl,
        ];

        return JWT::encode($payload, $this->settings->jwtSecret, 'HS256');
    }

    public function userId(string $token): int
    {
        $decoded = JWT::decode($token, new Key($this->settings->jwtSecret, 'HS256'));
        if (($decoded->iss ?? null) !== 'products-import') {
            throw new \UnexpectedValueException('Invalid token issuer');
        }
        $subject = $decoded->sub ?? null;
        if (!is_string($subject) || !preg_match('/^[1-9]\d*$/', $subject)) {
            throw new \UnexpectedValueException('Invalid token subject');
        }

        return (int) $subject;
    }
}
