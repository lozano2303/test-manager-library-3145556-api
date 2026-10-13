<?php

namespace Library\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * ISBN Value Object
 * 
 * Represents a valid 13-digit ISBN following domain rules.
 * Immutable value object with validation.
 * 
 * Rules:
 * - Must be exactly 13 digits
 * - Only numeric characters allowed
 * - Cannot be empty or null
 */
final readonly class Isbn
{
    public function __construct(
        private string $value
    ) {
        $this->validate($value);
    }

    /**
     * Create ISBN from string value
     */
    public static function from(string $value): self
    {
        return new self($value);
    }

    /**
     * Get the ISBN value as string
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Convert to string representation
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Check if two ISBNs are equal
     */
    public function equals(Isbn $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Validate ISBN format and rules
     * 
     * @throws InvalidArgumentException
     */
    private function validate(string $value): void
    {
        if (empty($value)) {
            throw new InvalidArgumentException('ISBN cannot be empty');
        }

        if (strlen($value) !== 13) {
            throw new InvalidArgumentException('ISBN must be exactly 13 digits');
        }

        if (!ctype_digit($value)) {
            throw new InvalidArgumentException('ISBN must contain only digits');
        }
    }
}