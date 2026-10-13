<?php

namespace Tests\Unit\Application\Fakes;

use Library\Application\Ports\Outbound\UserRepositoryPort;
use Library\Domain\Entities\User;

/**
 * Fake User Repository for Testing
 */
class FakeUserRepository implements UserRepositoryPort
{
    private array $users = [];
    private int $nextId = 1;

    public function save(User $user): void
    {
        $this->users[$user->getId()] = $user;
    }

    public function findByUsername(string $username): ?User
    {
        foreach ($this->users as $user) {
            if ($user->getUsername() === $username) {
                return $user;
            }
        }
        return null;
    }

    public function findById(string $id): ?User
    {
        return $this->users[$id] ?? null;
    }

    public function existsByUsername(string $username): bool
    {
        return $this->findByUsername($username) !== null;
    }

    public function nextId(): string
    {
        return (string) $this->nextId++;
    }

    // Test helper methods
    public function reset(): void
    {
        $this->users = [];
        $this->nextId = 1;
    }

    public function addUser(User $user): void
    {
        $this->users[$user->getId()] = $user;
    }

    public function getUserCount(): int
    {
        return count($this->users);
    }
}