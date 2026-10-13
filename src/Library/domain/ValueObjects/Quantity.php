<?php

namespace Library\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Quantity Value Object
 * 
 * Represents a positive integer quantity in the domain.
 * Used for book copies, loan limits, etc.
 * 
 * Rules:
 * - Must be greater than 0
 * - Must be an integer
 * - Immutable once created
 */
final readonly class Quantity
{
    public function __construct(
        private int $value
    ) {
        $this->validate($value);
    }

    /**
     * Create Quantity from integer value
     */
    public static function from(int $value): self
    {
        return new self($value);
    }

    /**
     * Create zero quantity (special case for available copies)
     */
    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * Get the quantity value as integer
     */
    public function value(): int
    {
        return $this->value;
    }

    /**
     * Convert to string representation
     */
    public function __toString(): string
    {
        return (string) $this->value;
    }

    /**
     * Check if two quantities are equal
     */
    public function equals(Quantity $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Check if quantity is greater than another
     */
    public function greaterThan(Quantity $other): bool
    {
        return $this->value > $other->value;
    }

    /**
     * Check if quantity is greater than or equal to another
     */
    public function greaterThanOrEqual(Quantity $other): bool
    {
        return $this->value >= $other->value;
    }

    /**
     * Check if quantity is less than another
     */
    public function lessThan(Quantity $other): bool
    {
        return $this->value < $other->value;
    }

    /**
     * Check if quantity is zero
     */
    public function isZero(): bool
    {
        return $this->value === 0;
    }

    /**
     * Check if quantity is positive (greater than zero)
     */
    public function isPositive(): bool
    {
        return $this->value > 0;
    }

    /**
     * Add another quantity (returns new instance)
     */
    public function add(Quantity $other): self
    {
        return new self($this->value + $other->value);
    }

    /**
     * Subtract another quantity (returns new instance)
     */
    public function subtract(Quantity $other): self
    {
        $result = $this->value - $other->value;
        
        if ($result < 0) {
            throw new InvalidArgumentException('Quantity cannot be negative after subtraction');
        }
        
        return new self($result);
    }

    /**
     * Validate quantity rules
     * 
     * @throws InvalidArgumentException
     */
    private function validate(int $value): void
    {
        if ($value < 0) {
            throw new InvalidArgumentException('Quantity must be greater than or equal to 0');
        }
    }
}