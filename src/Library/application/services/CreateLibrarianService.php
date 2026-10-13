<?php

namespace Library\Application\Services;

use Library\Application\Ports\Outbound\UserRepositoryPort;
use Library\Application\Ports\Outbound\PasswordHasherPort;
use Library\Domain\Entities\User;
use InvalidArgumentException;

/**
 * Create Librarian Service
 * 
 * Handles librarian user creation by admin (HU-02)
 */
class CreateLibrarianService
{
    public function __construct(
        private UserRepositoryPort $userRepository,
        private PasswordHasherPort $passwordHasher
    ) {}

    /**
     * Create a new librarian user
     * 
     * @throws InvalidArgumentException When username exists (CA-02.2) or role is admin (CA-02.3)
     */
    public function createLibrarian(string $username, string $password, string role): User
    {
        // CA-02.3: Reject admin role creation via API
        if (strtolower(trim($role)) === 'admin') {
            throw new InvalidArgumentException('Cannot create admin users via API');
        }
        
        // CA-02.1: Only allow librarian role
        if (strtolower(trim($role)) !== 'librarian') {
            throw new InvalidArgumentException('Invalid role. Only librarian role is allowed');
        }
        
        // CA-02.2: Username is stored lowercase and trimmed, must be unique
        $normalizedUsername = strtolower(trim($username));
        
        if ($this->userRepository->existsByUsername($normalizedUsername)) {
            throw new InvalidArgumentException('Username already exists');
        }
        
        // Hash password (CA-02.3: password never stored in plain text)
        $hashedPassword = $this->passwordHasher->hash($password);
        
        // Generate ID and create user
        $userId = $this->userRepository->nextId();
        $user = new User($userId, $normalizedUsername, $hashedPassword, 'librarian');
        
        $this->userRepository->save($user);
        
        return $user;
    }
}