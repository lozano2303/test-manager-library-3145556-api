<?php

namespace Library\Domain\Entities;

use Library\Domain\Services\Clock;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Loan Domain Entity
 * 
 * Represents a library loan with multiple loan items.
 * Manages loan state calculation and item operations.
 * 
 * Business Rules:
 * - 1 to 3 items per loan (RN-2)
 * - No duplicate books in same loan (CA-06.2)
 * - State is calculated, not stored (CA-08.3)
 * - Loans are immutable except for returns (RN-5)
 */
final class Loan
{
    /** @var LoanItem[] */
    private array $items;

    private function __construct(
        private string $id,
        private string $memberId,
        private string $loanedByUserId,
        private DateTimeImmutable $loanedAt,
        array $items = []
    ) {
        $this->items = $items;
        $this->validateItemConstraints();
    }

    /**
     * Create a new loan (factory method)
     */
    public static function create(
        string $id,
        string $memberId,
        string $loanedByUserId,
        DateTimeImmutable $loanedAt
    ): self {
        return new self(
            $id,
            $memberId,
            $loanedByUserId,
            $loanedAt,
            []
        );
    }

    /**
     * Reconstitute from persistence (for repositories)
     * 
     * @param LoanItem[] $items
     */
    public static function fromPersistence(
        string $id,
        string $memberId,
        string $loanedByUserId,
        DateTimeImmutable $loanedAt,
        array $items = []
    ): self {
        return new self(
            $id,
            $memberId,
            $loanedByUserId,
            $loanedAt,
            $items
        );
    }

    /**
     * Add an item to the loan
     * 
     * @throws InvalidArgumentException if constraints violated
     */
    public function addItem(
        string $itemId,
        string $bookId,
        string $bookTitle,
        string $isbn,
        string $categoryName
    ): self {
        // Check maximum items constraint (RN-2)
        if (count($this->items) >= 3) {
            throw new InvalidArgumentException('A loan cannot have more than 3 items');
        }

        // Check duplicate book constraint (CA-06.2)
        foreach ($this->items as $existingItem) {
            if ($existingItem->bookId() === $bookId) {
                throw new InvalidArgumentException('Book is already in this loan');
            }
        }

        $newItem = LoanItem::create(
            $itemId,
            $this->id,
            $bookId,
            $bookTitle,
            $isbn,
            $categoryName,
            $this->loanedAt
        );

        $newItems = [...$this->items, $newItem];

        return new self(
            $this->id,
            $this->memberId,
            $this->loanedByUserId,
            $this->loanedAt,
            $newItems
        );
    }

    /**
     * Return a specific item
     * 
     * @throws InvalidArgumentException if item not found or already returned
     */
    public function returnItem(string $itemId, ?DateTimeImmutable $returnedAt = null): self
    {
        $itemFound = false;
        $newItems = [];

        foreach ($this->items as $item) {
            if ($item->id() === $itemId) {
                $itemFound = true;
                $newItems[] = $item->returnItem($returnedAt); // This will validate if already returned
            } else {
                $newItems[] = $item;
            }
        }

        if (!$itemFound) {
            throw new InvalidArgumentException('Item not found in this loan');
        }

        return new self(
            $this->id,
            $this->memberId,
            $this->loanedByUserId,
            $this->loanedAt,
            $newItems
        );
    }

    /**
     * Calculate loan state based on current time
     * 
     * States:
     * - 'open': Has pending items, none overdue
     * - 'overdue': Has pending items, at least one overdue  
     * - 'closed': All items returned
     */
    public function calculateState(Clock $clock): string
    {
        $currentTime = $clock->now();
        
        $pendingItems = array_filter($this->items, fn($item) => $item->isPending());
        
        // If no pending items, loan is closed
        if (empty($pendingItems)) {
            return 'closed';
        }
        
        // Check if any pending item is overdue
        foreach ($pendingItems as $item) {
            if ($item->isOverdue($currentTime)) {
                return 'overdue';
            }
        }
        
        // Has pending items but none overdue
        return 'open';
    }

    // Getters

    public function id(): string
    {
        return $this->id;
    }

    public function memberId(): string
    {
        return $this->memberId;
    }

    public function loanedByUserId(): string
    {
        return $this->loanedByUserId;
    }

    public function loanedAt(): DateTimeImmutable
    {
        return $this->loanedAt;
    }

    /**
     * @return LoanItem[]
     */
    public function items(): array
    {
        return $this->items;
    }

    // Queries

    public function itemCount(): int
    {
        return count($this->items);
    }

    /**
     * Get pending (not returned) items
     * 
     * @return LoanItem[]
     */
    public function pendingItems(): array
    {
        return array_filter($this->items, fn($item) => $item->isPending());
    }

    /**
     * Get returned items
     * 
     * @return LoanItem[]
     */
    public function returnedItems(): array
    {
        return array_filter($this->items, fn($item) => $item->isReturned());
    }

    /**
     * Get overdue items
     * 
     * @return LoanItem[]
     */
    public function overdueItems(Clock $clock): array
    {
        $currentTime = $clock->now();
        return array_filter($this->items, fn($item) => $item->isOverdue($currentTime));
    }

    /**
     * Count pending items for member limits
     */
    public function pendingItemCount(): int
    {
        return count($this->pendingItems());
    }

    /**
     * Check if loan has overdue items
     */
    public function hasOverdueItems(Clock $clock): bool
    {
        return !empty($this->overdueItems($clock));
    }

    /**
     * Check if all items are returned
     */
    public function isFullyReturned(): bool
    {
        return empty($this->pendingItems());
    }

    /**
     * Check if loan can accept more items
     */
    public function canAddMoreItems(): bool
    {
        return count($this->items) < 3;
    }

    /**
     * Check if a specific book is already in this loan
     */
    public function hasBook(string $bookId): bool
    {
        foreach ($this->items as $item) {
            if ($item->bookId() === $bookId) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get item by ID
     */
    public function getItem(string $itemId): ?LoanItem
    {
        foreach ($this->items as $item) {
            if ($item->id() === $itemId) {
                return $item;
            }
        }
        return null;
    }

    /**
     * Validate business constraints
     */
    private function validateItemConstraints(): void
    {
        // Check maximum items (RN-2)
        if (count($this->items) > 3) {
            throw new InvalidArgumentException('A loan cannot have more than 3 items');
        }

        // Check for duplicate books (CA-06.2)
        $bookIds = [];
        foreach ($this->items as $item) {
            if (in_array($item->bookId(), $bookIds)) {
                throw new InvalidArgumentException('Duplicate books not allowed in same loan');
            }
            $bookIds[] = $item->bookId();
        }

        // Validate all items belong to this loan
        foreach ($this->items as $item) {
            if ($item->loanId() !== $this->id) {
                throw new InvalidArgumentException('Item does not belong to this loan');
            }
        }
    }

    /**
     * Domain equality
     */
    public function equals(Loan $other): bool
    {
        return $this->id === $other->id;
    }
}