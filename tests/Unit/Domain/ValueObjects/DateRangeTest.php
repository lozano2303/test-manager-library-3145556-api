<?php

namespace Tests\Unit\Domain\ValueObjects;

use Library\Domain\ValueObjects\DateRange;
use PHPUnit\Framework\TestCase;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Test for DateRange Value Object
 * 
 * Evidence for T-03: fin ≥ inicio (end >= start)
 * Tests all validation rules and date range operations
 */
class DateRangeTest extends TestCase
{
    public function testValidDateRangeIsCreated(): void
    {
        $start = new DateTimeImmutable('2024-01-01 00:00:00 UTC');
        $end = new DateTimeImmutable('2024-01-31 23:59:59 UTC');
        
        $range = DateRange::from($start, $end);
        
        $this->assertEquals($start, $range->start());
        $this->assertEquals($end, $range->end());
    }

    public function testEndDateMustBeGreaterOrEqualToStart(): void
    {
        $start = new DateTimeImmutable('2024-01-31 00:00:00 UTC');
        $end = new DateTimeImmutable('2024-01-01 00:00:00 UTC'); // Before start
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('End date must be greater than or equal to start date');
        
        DateRange::from($start, $end);
    }

    public function testSameDateRangeIsValid(): void
    {
        $date = new DateTimeImmutable('2024-01-01 12:00:00 UTC');
        
        $range = DateRange::from($date, $date);
        
        $this->assertEquals($date, $range->start());
        $this->assertEquals($date, $range->end());
        $this->assertEquals(0, $range->durationInDays());
    }

    public function testDateRangeFromStrings(): void
    {
        $range = DateRange::fromStrings('2024-01-01', '2024-01-31');
        
        $expectedStart = new DateTimeImmutable('2024-01-01 00:00:00 UTC');
        $expectedEnd = new DateTimeImmutable('2024-01-31 23:59:59 UTC');
        
        $this->assertEquals($expectedStart, $range->start());
        $this->assertEquals($expectedEnd, $range->end());
    }

    public function testDateRangeFromStringsInvalidFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid date format. Expected Y-m-d format.');
        
        DateRange::fromStrings('invalid-date', '2024-01-31');
    }

    public function testLoanPeriodRange(): void
    {
        $loanDate = new DateTimeImmutable('2024-01-01 10:00:00 UTC');
        
        $range = DateRange::loanPeriod($loanDate);
        
        $expectedEnd = $loanDate->modify('+14 days');
        
        $this->assertEquals($loanDate, $range->start());
        $this->assertEquals($expectedEnd, $range->end());
        $this->assertEquals(14, $range->durationInDays());
    }

    public function testDateRangeEquality(): void
    {
        $start = new DateTimeImmutable('2024-01-01 00:00:00 UTC');
        $end = new DateTimeImmutable('2024-01-31 23:59:59 UTC');
        
        $range1 = DateRange::from($start, $end);
        $range2 = DateRange::from($start, $end);
        $range3 = DateRange::from($start, $start->modify('+10 days'));
        
        $this->assertTrue($range1->equals($range2));
        $this->assertFalse($range1->equals($range3));
    }

    public function testDateRangeContains(): void
    {
        $start = new DateTimeImmutable('2024-01-01 00:00:00 UTC');
        $end = new DateTimeImmutable('2024-01-31 23:59:59 UTC');
        $range = DateRange::from($start, $end);
        
        $dateInside = new DateTimeImmutable('2024-01-15 12:00:00 UTC');
        $dateOutside = new DateTimeImmutable('2024-02-01 00:00:00 UTC');
        $dateBefore = new DateTimeImmutable('2023-12-31 23:59:59 UTC');
        
        $this->assertTrue($range->contains($dateInside));
        $this->assertTrue($range->contains($start)); // Boundary inclusive
        $this->assertTrue($range->contains($end)); // Boundary inclusive
        $this->assertFalse($range->contains($dateOutside));
        $this->assertFalse($range->contains($dateBefore));
    }

    public function testDateRangeOverlaps(): void
    {
        $range1 = DateRange::fromStrings('2024-01-01', '2024-01-15');
        $range2 = DateRange::fromStrings('2024-01-10', '2024-01-25'); // Overlaps
        $range3 = DateRange::fromStrings('2024-01-20', '2024-01-31'); // No overlap
        $range4 = DateRange::fromStrings('2024-01-15', '2024-01-20'); // Touches at boundary
        
        $this->assertTrue($range1->overlaps($range2));
        $this->assertTrue($range2->overlaps($range1)); // Symmetric
        $this->assertFalse($range1->overlaps($range3));
        $this->assertTrue($range1->overlaps($range4)); // Boundary counts as overlap
    }

    public function testDateRangeDurationInDays(): void
    {
        $start = new DateTimeImmutable('2024-01-01 00:00:00 UTC');
        $end = new DateTimeImmutable('2024-01-08 00:00:00 UTC'); // 7 days later
        
        $range = DateRange::from($start, $end);
        
        $this->assertEquals(7, $range->durationInDays());
    }

    public function testDateRangeHasExpired(): void
    {
        $pastStart = new DateTimeImmutable('2023-01-01 00:00:00 UTC');
        $pastEnd = new DateTimeImmutable('2023-01-31 23:59:59 UTC');
        $pastRange = DateRange::from($pastStart, $pastEnd);
        
        $futureStart = new DateTimeImmutable('2025-01-01 00:00:00 UTC');
        $futureEnd = new DateTimeImmutable('2025-01-31 23:59:59 UTC');
        $futureRange = DateRange::from($futureStart, $futureEnd);
        
        $currentDate = new DateTimeImmutable('2024-06-15 12:00:00 UTC');
        
        $this->assertTrue($pastRange->hasExpired($currentDate));
        $this->assertFalse($futureRange->hasExpired($currentDate));
    }

    public function testDateRangeDaysOverdue(): void
    {
        $start = new DateTimeImmutable('2024-01-01 00:00:00 UTC');
        $end = new DateTimeImmutable('2024-01-10 23:59:59 UTC');
        $range = DateRange::from($start, $end);
        
        $currentDate = new DateTimeImmutable('2024-01-15 12:00:00 UTC'); // 5 days after end
        
        $this->assertEquals(5, $range->daysOverdue($currentDate));
    }

    public function testDateRangeDaysOverdueNotOverdue(): void
    {
        $start = new DateTimeImmutable('2024-01-01 00:00:00 UTC');
        $end = new DateTimeImmutable('2024-01-31 23:59:59 UTC');
        $range = DateRange::from($start, $end);
        
        $currentDate = new DateTimeImmutable('2024-01-15 12:00:00 UTC'); // Still within range
        
        $this->assertEquals(0, $range->daysOverdue($currentDate));
    }

    public function testDateRangeToString(): void
    {
        $start = new DateTimeImmutable('2024-01-01 00:00:00 UTC');
        $end = new DateTimeImmutable('2024-01-31 23:59:59 UTC');
        $range = DateRange::from($start, $end);
        
        $expected = '2024-01-01 00:00:00 to 2024-01-31 23:59:59';
        
        $this->assertEquals($expected, (string) $range);
    }

    /**
     * Data provider for valid date range scenarios
     */
    public function validDateRangeProvider(): array
    {
        return [
            ['2024-01-01', '2024-01-01'], // Same day
            ['2024-01-01', '2024-01-02'], // Next day
            ['2024-01-01', '2024-12-31'], // Full year
            ['2024-02-28', '2024-02-29'], // Leap year
        ];
    }

    /**
     * @dataProvider validDateRangeProvider
     */
    public function testValidDateRangesAreAccepted(string $startDate, string $endDate): void
    {
        $range = DateRange::fromStrings($startDate, $endDate);
        
        $this->assertInstanceOf(DateRange::class, $range);
        $this->assertLessThanOrEqual($range->end(), $range->start());
    }

    /**
     * Data provider for invalid date range scenarios (end before start)
     */
    public function invalidDateRangeProvider(): array
    {
        return [
            ['2024-01-02', '2024-01-01'], // End before start
            ['2024-12-31', '2024-01-01'], // End much before start
            ['2024-02-29', '2024-02-28'], // Leap year edge case
        ];
    }

    /**
     * @dataProvider invalidDateRangeProvider
     */
    public function testInvalidDateRangesAreRejected(string $startDate, string $endDate): void
    {
        $this->expectException(InvalidArgumentException::class);
        
        DateRange::fromStrings($startDate, $endDate);
    }
}