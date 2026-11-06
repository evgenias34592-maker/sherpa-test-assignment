<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;

final class Authenticator
{
    private const DUMMY_HASH = '$2y$10$WQpOUms2QU7fR0kIfoAWUOEsztQ39pEd3JN7FsQwup2FhM1O3frUO';

    public function __construct(private readonly UserRepository $users)
    {
    }

    public function authenticate(string $email, string $password): ?User
    {
        $email = strtolower(trim($email));
        $user = $this->users->findByEmail($email);
        $hash = $user?->passwordHash() ?? self::DUMMY_HASH;
        if (!password_verify($password, $hash) || $user === null) {
            return null;
        }

        return $user;
    }
}
