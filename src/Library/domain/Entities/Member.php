<?php

namespace Library\Domain\Entities;

use InvalidArgumentException;

/**
 * Member Domain Entity
 * 
 * Represents a library member who can borrow books.
 * Members are people who request loans but do not access the system.
 * 
 * Business Rules:
 * - Full name normalization (trim and lowercase)
 * - Document number uniqueness 
 * - Members cannot be deleted (only loan history)
 */
final class Member
{
    private function __construct(
        private string $id,
        private string $fullName,
        private string $documentNumber
    ) {
        $this->validateMember();
    }

    /**
     * Create a new member (factory method)
     */
    public static function create(
        string $id,
        string $fullName,
        string $documentNumber
    ): self {
        return new self(
            $id,
            self::normalizeName($fullName),
            trim($documentNumber)
        );
    }

    /**
     * Reconstitute from persistence (for repositories)
     */
    public static function fromPersistence(
        string $id,
        string $fullName,
        string $documentNumber
    ): self {
        return new self(
            $id,
            $fullName, // Already normalized in persistence
            $documentNumber
        );
    }

    /**
     * Update member information
     */
    public function updateInfo(string $fullName, string $documentNumber): self
    {
        return new self(
            $this->id,
            self::normalizeName($fullName),
            trim($documentNumber)
        );
    }

    /**
     * Normalize member name according to business rules
     * 
     * T-06 Evidence: "Ana " → "ana"
     * - Trim whitespace
     * - Convert to lowercase
     * - Handle multiple spaces
     */
    private static function normalizeName(string $name): string
    {
        // Trim leading and trailing whitespace
        $normalized = trim($name);
        
        // Convert to lowercase for storage consistency
        $normalized = mb_strtolower($normalized, 'UTF-8');
        
        // Collapse multiple internal spaces to single space
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        
        return $normalized;
    }

    // Getters

    public function id(): string
    {
        return $this->id;
    }

    public function fullName(): string
    {
        return $this->fullName;
    }

    public function documentNumber(): string
    {
        return $this->documentNumber;
    }

    // Queries

    /**
     * Get display name (proper case for UI)
     */
    public function displayName(): string
    {
        // Convert back to title case for display
        return mb_convert_case($this->fullName, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Check if member matches search term
     */
    public function matchesSearch(string $searchTerm): bool
    {
        $normalizedSearch = mb_strtolower(trim($searchTerm), 'UTF-8');
        
        return str_contains($this->fullName, $normalizedSearch) ||
               str_contains($this->documentNumber, $normalizedSearch);
    }

    /**
     * Validate member data
     */
    private function validateMember(): void
    {
        if (empty(trim($this->fullName))) {
            throw new InvalidArgumentException('Member full name cannot be empty');
        }

        if (empty(trim($this->documentNumber))) {
            throw new InvalidArgumentException('Member document number cannot be empty');
        }

        // Document number basic format validation (alphanumeric)
        if (!preg_match('/^[a-zA-Z0-9\-\.]+$/', $this->documentNumber)) {
            throw new InvalidArgumentException('Document number contains invalid characters');
        }
    }

    /**
     * Domain equality
     */
    public function equals(Member $other): bool
    {
        return $this->id === $other->id;
    }
}