<?php

namespace Library\Domain\Entities;

use InvalidArgumentException;

/**
 * User Domain Entity
 * 
 * Represents internal system operators (admin, librarian).
 * Users authenticate and perform operations based on their role.
 * 
 * Business Rules:
 * - Username normalization (trim and lowercase)
 * - Role validation (admin, librarian only)
 * - Admin role cannot be requested via API (CA-02.3)
 * - Password hash never exposed
 */
final class User
{
    private const VALID_ROLES = ['admin', 'librarian'];
    private const API_ALLOWED_ROLES = ['librarian']; // Admin cannot be created via API

    private function __construct(
        private string $id,
        private string $username,
        private string $passwordHash,
        private string $role
    ) {
        $this->validateUser();
    }

    /**
     * Create a new user (factory method for system creation)
     */
    public static function create(
        string $id,
        string $username,
        string $passwordHash,
        string $role
    ): self {
        return new self(
            $id,
            self::normalizeUsername($username),
            $passwordHash,
            $role
        );
    }

    /**
     * Create a new user via API (restricted roles)
     * 
     * T-06 Evidence: rol `admin` por API rechazado
     */
    public static function createViaApi(
        string $id,
        string $username,
        string $passwordHash,
        string $role
    ): self {
        if (!in_array($role, self::API_ALLOWED_ROLES)) {
            throw new InvalidArgumentException(
                sprintf('Role "%s" cannot be created via API. Allowed roles: %s', 
                    $role, 
                    implode(', ', self::API_ALLOWED_ROLES)
                )
            );
        }

        return self::create($id, $username, $passwordHash, $role);
    }

    /**
     * Reconstitute from persistence (for repositories)
     */
    public static function fromPersistence(
        string $id,
        string $username,
        string $passwordHash,
        string $role
    ): self {
        return new self(
            $id,
            $username, // Already normalized in persistence
            $passwordHash,
            $role
        );
    }

    /**
     * Update user password
     */
    public function updatePassword(string $newPasswordHash): self
    {
        return new self(
            $this->id,
            $this->username,
            $newPasswordHash,
            $this->role
        );
    }

    /**
     * Update user role (system operation only)
     */
    public function updateRole(string $newRole): self
    {
        return new self(
            $this->id,
            $this->username,
            $this->passwordHash,
            $newRole
        );
    }

    /**
     * Normalize username according to business rules
     * 
     * T-06 Evidence: Username normalization
     * - Trim whitespace
     * - Convert to lowercase
     * - Remove multiple spaces
     */
    private static function normalizeUsername(string $username): string
    {
        // Trim leading and trailing whitespace
        $normalized = trim($username);
        
        // Convert to lowercase for consistency
        $normalized = mb_strtolower($normalized, 'UTF-8');
        
        // Remove any internal spaces (usernames should not have spaces)
        $normalized = preg_replace('/\s+/', '', $normalized);
        
        return $normalized;
    }

    // Getters

    public function id(): string
    {
        return $this->id;
    }

    public function username(): string
    {
        return $this->username;
    }

    /**
     * Get password hash (internal use only)
     * 
     * Note: This should never be exposed in API responses or logs (RNF-3)
     */
    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function role(): string
    {
        return $this->role;
    }

    // Role queries

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isLibrarian(): bool
    {
        return $this->role === 'librarian';
    }

    /**
     * Check if user has permission for operation
     */
    public function hasRole(string $requiredRole): bool
    {
        return $this->role === $requiredRole;
    }

    /**
     * Check if user can perform admin operations
     */
    public function canPerformAdminOperations(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Check if user can perform librarian operations
     */
    public function canPerformLibrarianOperations(): bool
    {
        return $this->isAdmin() || $this->isLibrarian();
    }

    /**
     * Get valid roles for the system
     */
    public static function getValidRoles(): array
    {
        return self::VALID_ROLES;
    }

    /**
     * Get API-allowed roles (excludes admin)
     */
    public static function getApiAllowedRoles(): array
    {
        return self::API_ALLOWED_ROLES;
    }

    /**
     * Validate user data
     */
    private function validateUser(): void
    {
        // Username validation
        if (empty(trim($this->username))) {
            throw new InvalidArgumentException('Username cannot be empty');
        }

        if (strlen($this->username) < 3) {
            throw new InvalidArgumentException('Username must be at least 3 characters long');
        }

        if (strlen($this->username) > 120) {
            throw new InvalidArgumentException('Username cannot exceed 120 characters');
        }

        // Username format validation (alphanumeric and some symbols)
        if (!preg_match('/^[a-z0-9._-]+$/', $this->username)) {
            throw new InvalidArgumentException('Username contains invalid characters');
        }

        // Password hash validation
        if (empty($this->passwordHash)) {
            throw new InvalidArgumentException('Password hash cannot be empty');
        }

        // Role validation
        if (!in_array($this->role, self::VALID_ROLES)) {
            throw new InvalidArgumentException(
                sprintf('Invalid role "%s". Valid roles: %s', 
                    $this->role, 
                    implode(', ', self::VALID_ROLES)
                )
            );
        }
    }

    /**
     * Domain equality
     */
    public function equals(User $other): bool
    {
        return $this->id === $other->id;
    }

    /**
     * Convert to array for logging (excludes password hash)
     */
    public function toLogArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'role' => $this->role
            // Intentionally excludes passwordHash for security (RNF-3)
        ];
    }
}