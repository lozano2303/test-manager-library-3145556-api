<?php

namespace Library\Domain\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * DateRange Value Object
 * 
 * Represents a range of dates with start and end boundaries.
 * Used for loan periods, report ranges, etc.
 * 
 * Rules:
 * - End date must be >= start date
 * - Both dates are required
 * - Immutable once created
 * - All dates are in UTC timezone
 */
final readonly class DateRange
{
    public function __construct(
        private DateTimeImmutable $start,
        private DateTimeImmutable $end
    ) {
        $this->validate($start, $end);
    }

    /**
     * Create DateRange from two DateTimeImmutable objects
     */
    public static function from(DateTimeImmutable $start, DateTimeImmutable $end): self
    {
        return new self($start, $end);
    }

    /**
     * Create DateRange from string dates (Y-m-d format)
     */
    public static function fromStrings(string $startDate, string $endDate): self
    {
        try {
            $start = new DateTimeImmutable($startDate . ' 00:00:00 UTC');
            $end = new DateTimeImmutable($endDate . ' 23:59:59 UTC');
            
            return new self($start, $end);
        } catch (\Exception $e) {
            throw new InvalidArgumentException('Invalid date format. Expected Y-m-d format.', 0, $e);
        }
    }

    /**
     * Create a loan period range (start date + 14 days)
     */
    public static function loanPeriod(DateTimeImmutable $loanDate): self
    {
        $dueDate = $loanDate->modify('+14 days');
        return new self($loanDate, $dueDate);
    }

    /**
     * Get start date
     */
    public function start(): DateTimeImmutable
    {
        return $this->start;
    }

    /**
     * Get end date
     */
    public function end(): DateTimeImmutable
    {
        return $this->end;
    }

    /**
     * Check if two date ranges are equal
     */
    public function equals(DateRange $other): bool
    {
        return $this->start->getTimestamp() === $other->start->getTimestamp() &&
               $this->end->getTimestamp() === $other->end->getTimestamp();
    }

    /**
     * Check if a date is within this range (inclusive)
     */
    public function contains(DateTimeImmutable $date): bool
    {
        return $date >= $this->start && $date <= $this->end;
    }

    /**
     * Check if this range overlaps with another range
     */
    public function overlaps(DateRange $other): bool
    {
        return $this->start <= $other->end && $this->end >= $other->start;
    }

    /**
     * Get the duration in days
     */
    public function durationInDays(): int
    {
        return $this->start->diff($this->end)->days;
    }

    /**
     * Check if the range has expired (end date is in the past)
     */
    public function hasExpired(DateTimeImmutable $currentDate = null): bool
    {
        $now = $currentDate ?? new DateTimeImmutable('now', new \DateTimeZone('UTC'));
        return $this->end < $now;
    }

    /**
     * Get days overdue (0 if not overdue)
     */
    public function daysOverdue(DateTimeImmutable $currentDate = null): int
    {
        $now = $currentDate ?? new DateTimeImmutable('now', new \DateTimeZone('UTC'));
        
        if ($this->end >= $now) {
            return 0; // Not overdue
        }
        
        return $now->diff($this->end)->days;
    }

    /**
     * Convert to string representation
     */
    public function __toString(): string
    {
        return sprintf(
            '%s to %s',
            $this->start->format('Y-m-d H:i:s'),
            $this->end->format('Y-m-d H:i:s')
        );
    }

    /**
     * Validate date range rules
     * 
     * @throws InvalidArgumentException
     */
    private function validate(DateTimeImmutable $start, DateTimeImmutable $end): void
    {
        if ($end < $start) {
            throw new InvalidArgumentException('End date must be greater than or equal to start date');
        }
    }
}