<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class UserRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function add(User $user): void
    {
        $this->em->persist($user);
    }

    public function findByEmail(string $email): ?User
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);

        return $user instanceof User ? $user : null;
    }

    public function find(int $id): ?User
    {
        $user = $this->em->find(User::class, $id);

        return $user instanceof User ? $user : null;
    }
}
