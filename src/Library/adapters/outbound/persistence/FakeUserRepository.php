<?php

namespace Library\Adapters\Outbound\Persistence;

use Library\Application\Ports\Outbound\UserRepositoryPort;
use Library\Domain\Entities\User;

/**
 * Fake User Repository
 * 
 * In-memory implementation for testing.
 * Maintains consistency with domain rules.
 */
final class FakeUserRepository implements UserRepositoryPort
{
    /** @var User[] */
    private array $users = [];
    
    /** @var string[] */
    private array $usernameIndex = [];
    
    private int $nextIdCounter = 1;

    public function save(User $user): void
    {
        $this->users[$user->id()] = $user;
        $this->usernameIndex[$user->username()] = $user->id();
    }

    public function findByUsername(string $username): ?User
    {
        $userId = $this->usernameIndex[$username] ?? null;
        return $userId ? $this->users[$userId] : null;
    }

    public function findById(string $id): ?User
    {
        return $this->users[$id] ?? null;
    }

    public function existsByUsername(string $username): bool
    {
        return isset($this->usernameIndex[$username]);
    }

    public function nextId(): string
    {
        return 'user-' . $this->nextIdCounter++;
    }

    /**
     * Test helper: clear all data
     */
    public function clear(): void
    {
        $this->users = [];
        $this->usernameIndex = [];
        $this->nextIdCounter = 1;
    }

    /**
     * Test helper: get all users
     */
    public function getAll(): array
    {
        return array_values($this->users);
    }

    /**
     * Test helper: seed with test data
     */
    public function seed(): void
    {
        // Create admin user for testing
        $admin = User::create(
            $this->nextId(),
            'admin',
            'hashed:admin123',
            'admin'
        );
        $this->save($admin);

        // Create librarian user for testing
        $librarian = User::create(
            $this->nextId(),
            'librarian',
            'hashed:lib123',
            'librarian'
        );
        $this->save($librarian);
    }
}