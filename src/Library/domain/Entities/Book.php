<?php

namespace Library\Domain\Entities;

use Library\Domain\ValueObjects\Isbn;
use Library\Domain\ValueObjects\Quantity;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Book Domain Entity
 * 
 * Represents a book in the library catalog with copy management.
 * Handles lending (withdraw) and returning (restock) operations.
 * 
 * Business Rules:
 * - Available copies: 0 <= available_copies <= total_copies (RN-1)
 * - Total copies must be >= 1
 * - Logical deletion only (RN-5)
 * - Immutable book data once created
 */
final class Book
{
    private function __construct(
        private string $id,
        private string $title,
        private string $author,
        private Isbn $isbn,
        private string $categoryId,
        private Quantity $totalCopies,
        private Quantity $availableCopies,
        private ?string $coverKey = null,
        private ?DateTimeImmutable $deletedAt = null
    ) {
        $this->validateCopyConstraints();
    }

    /**
     * Create a new book (factory method)
     */
    public static function create(
        string $id,
        string $title,
        string $author,
        Isbn $isbn,
        string $categoryId,
        Quantity $totalCopies,
        ?string $coverKey = null
    ): self {
        if ($totalCopies->value() < 1) {
            throw new InvalidArgumentException('Total copies must be at least 1');
        }

        // Available copies start equal to total copies
        $availableCopies = Quantity::from($totalCopies->value());

        return new self(
            $id,
            trim($title),
            $author,
            $isbn,
            $categoryId,
            $totalCopies,
            $availableCopies,
            $coverKey
        );
    }

    /**
     * Reconstitute from persistence (for repositories)
     */
    public static function fromPersistence(
        string $id,
        string $title,
        string $author,
        Isbn $isbn,
        string $categoryId,
        Quantity $totalCopies,
        Quantity $availableCopies,
        ?string $coverKey = null,
        ?DateTimeImmutable $deletedAt = null
    ): self {
        return new self(
            $id,
            $title,
            $author,
            $isbn,
            $categoryId,
            $totalCopies,
            $availableCopies,
            $coverKey,
            $deletedAt
        );
    }

    /**
     * Withdraw copies (for lending)
     * 
     * @throws InvalidArgumentException if not enough copies available
     */
    public function withdraw(Quantity $quantity): self
    {
        if ($quantity->value() <= 0) {
            throw new InvalidArgumentException('Withdraw quantity must be positive');
        }

        if ($this->availableCopies->lessThan($quantity)) {
            throw new InvalidArgumentException(
                sprintf('Insufficient copies. Available: %d, Requested: %d', 
                    $this->availableCopies->value(), 
                    $quantity->value()
                )
            );
        }

        $newAvailable = $this->availableCopies->subtract($quantity);

        return new self(
            $this->id,
            $this->title,
            $this->author,
            $this->isbn,
            $this->categoryId,
            $this->totalCopies,
            $newAvailable,
            $this->coverKey,
            $this->deletedAt
        );
    }

    /**
     * Restock copies (for returning)
     * 
     * @throws InvalidArgumentException if would exceed total copies
     */
    public function restock(Quantity $quantity): self
    {
        if ($quantity->value() <= 0) {
            throw new InvalidArgumentException('Restock quantity must be positive');
        }

        $newAvailable = $this->availableCopies->add($quantity);

        if ($newAvailable->greaterThan($this->totalCopies)) {
            throw new InvalidArgumentException(
                sprintf('Cannot exceed total copies. Total: %d, Would be: %d', 
                    $this->totalCopies->value(), 
                    $newAvailable->value()
                )
            );
        }

        return new self(
            $this->id,
            $this->title,
            $this->author,
            $this->isbn,
            $this->categoryId,
            $this->totalCopies,
            $newAvailable,
            $this->coverKey,
            $this->deletedAt
        );
    }

    /**
     * Update total copies (adjusts available proportionally)
     */
    public function updateTotalCopies(Quantity $newTotal): self
    {
        if ($newTotal->value() < 1) {
            throw new InvalidArgumentException('Total copies must be at least 1');
        }

        // Calculate the difference
        $difference = $newTotal->value() - $this->totalCopies->value();
        
        // Adjust available copies by the same difference
        $newAvailableValue = $this->availableCopies->value() + $difference;
        
        // Ensure available never goes below 0 (CA-03.3)
        if ($newAvailableValue < 0) {
            throw new InvalidArgumentException(
                sprintf('Cannot reduce total copies. Would make available negative: %d', $newAvailableValue)
            );
        }

        $newAvailable = Quantity::from($newAvailableValue);

        return new self(
            $this->id,
            $this->title,
            $this->author,
            $this->isbn,
            $this->categoryId,
            $newTotal,
            $newAvailable,
            $this->coverKey,
            $this->deletedAt
        );
    }

    /**
     * Update book metadata
     */
    public function updateMetadata(string $title, string $author, string $categoryId): self
    {
        return new self(
            $this->id,
            trim($title),
            $author,
            $this->isbn,
            $categoryId,
            $this->totalCopies,
            $this->availableCopies,
            $this->coverKey,
            $this->deletedAt
        );
    }

    /**
     * Set cover key
     */
    public function setCover(?string $coverKey): self
    {
        // Never allow empty string, only null or non-empty string (CA-03.4)
        if ($coverKey === '') {
            $coverKey = null;
        }

        return new self(
            $this->id,
            $this->title,
            $this->author,
            $this->isbn,
            $this->categoryId,
            $this->totalCopies,
            $this->availableCopies,
            $coverKey,
            $this->deletedAt
        );
    }

    /**
     * Logical deletion (CA-03.5)
     */
    public function delete(?DateTimeImmutable $deletedAt = null): self
    {
        $deletedAt = $deletedAt ?? new DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return new self(
            $this->id,
            $this->title,
            $this->author,
            $this->isbn,
            $this->categoryId,
            $this->totalCopies,
            $this->availableCopies,
            $this->coverKey,
            $deletedAt
        );
    }

    /**
     * Restore from logical deletion
     */
    public function restore(): self
    {
        return new self(
            $this->id,
            $this->title,
            $this->author,
            $this->isbn,
            $this->categoryId,
            $this->totalCopies,
            $this->availableCopies,
            $this->coverKey,
            null
        );
    }

    // Getters

    public function id(): string
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function author(): string
    {
        return $this->author;
    }

    public function isbn(): Isbn
    {
        return $this->isbn;
    }

    public function categoryId(): string
    {
        return $this->categoryId;
    }

    public function totalCopies(): Quantity
    {
        return $this->totalCopies;
    }

    public function availableCopies(): Quantity
    {
        return $this->availableCopies;
    }

    public function coverKey(): ?string
    {
        return $this->coverKey;
    }

    public function deletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    // Status queries

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function isActive(): bool
    {
        return !$this->isDeleted();
    }

    public function isAvailable(): bool
    {
        return $this->isActive() && $this->availableCopies->isPositive();
    }

    public function hasCover(): bool
    {
        return $this->coverKey !== null;
    }

    public function canWithdraw(Quantity $quantity): bool
    {
        return $this->isActive() && 
               $this->availableCopies->greaterThanOrEqual($quantity);
    }

    public function canRestock(Quantity $quantity): bool
    {
        $wouldBe = $this->availableCopies->add($quantity);
        return $wouldBe->lessThanOrEqual($this->totalCopies);
    }

    /**
     * Check if book has open loans (for deletion validation)
     * This is a query method - the actual validation happens in the application layer
     */
    public function canBeDeleted(): bool
    {
        // Basic domain rules - actual loan checking happens in application layer
        return $this->isActive();
    }

    /**
     * Validate copy constraints (RN-1)
     */
    private function validateCopyConstraints(): void
    {
        if ($this->availableCopies->greaterThan($this->totalCopies)) {
            throw new InvalidArgumentException(
                sprintf('Available copies (%d) cannot exceed total copies (%d)', 
                    $this->availableCopies->value(), 
                    $this->totalCopies->value()
                )
            );
        }

        if ($this->totalCopies->value() < 1) {
            throw new InvalidArgumentException('Total copies must be at least 1');
        }
    }

    /**
     * Domain equality
     */
    public function equals(Book $other): bool
    {
        return $this->id === $other->id;
    }
}