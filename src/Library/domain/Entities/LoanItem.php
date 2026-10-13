<?php

namespace Library\Domain\Entities;

use Library\Domain\ValueObjects\DateRange;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Loan Item Entity
 * 
 * Represents one book within a loan with its due date and return status.
 * Encapsulates frozen book data at the time of loan.
 * 
 * Business Rules:
 * - Due date is loan date + 14 days (RN-2)
 * - Book data is frozen at loan time (RN-4)  
 * - Return is immutable once set (RN-5)
 */
final class LoanItem
{
    private function __construct(
        private string $id,
        private string $loanId,
        private string $bookId,
        private string $bookTitle,
        private string $isbn,
        private string $categoryName,
        private DateTimeImmutable $dueAt,
        private ?DateTimeImmutable $returnedAt = null
    ) {}

    /**
     * Create a new loan item (factory method)
     */
    public static function create(
        string $id,
        string $loanId,
        string $bookId,
        string $bookTitle,
        string $isbn,
        string $categoryName,
        DateTimeImmutable $loanedAt
    ): self {
        // Due date is loaned_at + 14 days (RN-2)
        $dueAt = $loanedAt->modify('+14 days');

        return new self(
            $id,
            $loanId,
            $bookId,
            trim($bookTitle), // Frozen title
            $isbn, // Frozen ISBN
            $categoryName, // Frozen category name  
            $dueAt
        );
    }

    /**
     * Reconstitute from persistence (for repositories)
     */
    public static function fromPersistence(
        string $id,
        string $loanId,
        string $bookId,
        string $bookTitle,
        string $isbn,
        string $categoryName,
        DateTimeImmutable $dueAt,
        ?DateTimeImmutable $returnedAt = null
    ): self {
        return new self(
            $id,
            $loanId,
            $bookId,
            $bookTitle,
            $isbn,
            $categoryName,
            $dueAt,
            $returnedAt
        );
    }

    /**
     * Return this item
     * 
     * @throws InvalidArgumentException if already returned
     */
    public function returnItem(?DateTimeImmutable $returnedAt = null): self
    {
        if ($this->isReturned()) {
            throw new InvalidArgumentException('Item is already returned');
        }

        $returnedAt = $returnedAt ?? new DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return new self(
            $this->id,
            $this->loanId,
            $this->bookId,
            $this->bookTitle,
            $this->isbn,
            $this->categoryName,
            $this->dueAt,
            $returnedAt
        );
    }

    // Getters

    public function id(): string
    {
        return $this->id;
    }

    public function loanId(): string
    {
        return $this->loanId;
    }

    public function bookId(): string
    {
        return $this->bookId;
    }

    public function bookTitle(): string
    {
        return $this->bookTitle;
    }

    public function isbn(): string
    {
        return $this->isbn;
    }

    public function categoryName(): string
    {
        return $this->categoryName;
    }

    public function dueAt(): DateTimeImmutable
    {
        return $this->dueAt;
    }

    public function returnedAt(): ?DateTimeImmutable
    {
        return $this->returnedAt;
    }

    // Status queries

    public function isReturned(): bool
    {
        return $this->returnedAt !== null;
    }

    public function isPending(): bool
    {
        return !$this->isReturned();
    }

    /**
     * Check if this item is overdue (past due date and not returned)
     */
    public function isOverdue(DateTimeImmutable $currentTime): bool
    {
        return $this->isPending() && $currentTime > $this->dueAt;
    }

    /**
     * Get days overdue (0 if not overdue)
     */
    public function daysOverdue(DateTimeImmutable $currentTime): int
    {
        if (!$this->isOverdue($currentTime)) {
            return 0;
        }

        return $currentTime->diff($this->dueAt)->days;
    }

    /**
     * Get loan period as DateRange
     */
    public function loanPeriod(): DateRange
    {
        // Calculate loan start from due date - 14 days
        $loanedAt = $this->dueAt->modify('-14 days');
        return DateRange::from($loanedAt, $this->dueAt);
    }

    /**
     * Domain equality
     */
    public function equals(LoanItem $other): bool
    {
        return $this->id === $other->id;
    }
}