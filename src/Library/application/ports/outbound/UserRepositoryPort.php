<?php

namespace Library\Application\Ports\Outbound;

use Library\Domain\Entities\User;

/**
 * User Repository Port
 * 
 * Defines the contract for user persistence operations.
 * Follows hexagonal architecture - application layer depends on this abstraction.
 */
interface UserRepositoryPort
{
    /**
     * Save a user (create or update)
     */
    public function save(User $user): void;

    /**
     * Find user by username
     */
    public function findByUsername(string $username): ?User;

    /**
     * Find user by ID
     */
    public function findById(string $id): ?User;

    /**
     * Check if username exists
     */
    public function existsByUsername(string $username): bool;

    /**
     * Generate next user ID
     */
    public function nextId(): string;
}