<?php

namespace Tests\Unit\Domain\Entities;

use Library\Domain\Entities\LoanItem;
use Tests\Unit\Domain\Services\FakeClock;
use PHPUnit\Framework\TestCase;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Test for LoanItem Entity
 * 
 * Supporting tests for T-05 evidence - validates loan item behavior
 */
class LoanItemTest extends TestCase
{
    public function testLoanItemCreation(): void
    {
        $loanedAt = new DateTimeImmutable('2024-01-01 10:00:00 UTC');
        
        $item = LoanItem::create(
            id: 'item-123',
            loanId: 'loan-456',
            bookId: 'book-789',
            bookTitle: '  Clean Architecture  ', // Test title trimming
            isbn: '9780134494166',
            categoryName: 'Tecnología',
            loanedAt: $loanedAt
        );

        $this->assertEquals('item-123', $item->id());
        $this->assertEquals('loan-456', $item->loanId());
        $this->assertEquals('book-789', $item->bookId());
        $this->assertEquals('Clean Architecture', $item->bookTitle()); // Trimmed
        $this->assertEquals('9780134494166', $item->isbn());
        $this->assertEquals('Tecnología', $item->categoryName());
        
        // Due date should be loaned_at + 14 days
        $expectedDue = $loanedAt->modify('+14 days');
        $this->assertEquals($expectedDue, $item->dueAt());
        
        $this->assertNull($item->returnedAt());
        $this->assertFalse($item->isReturned());
        $this->assertTrue($item->isPending());
    }

    public function testLoanItemReturn(): void
    {
        $loanedAt = new DateTimeImmutable('2024-01-01 10:00:00 UTC');
        $returnedAt = new DateTimeImmutable('2024-01-05 14:30:00 UTC');
        
        $item = LoanItem::create(
            'item-123', 'loan-456', 'book-789',
            'Clean Architecture', '9780134494166', 'Tecnología',
            $loanedAt
        );

        $returnedItem = $item->returnItem($returnedAt);

        // Original item unchanged
        $this->assertNull($item->returnedAt());
        $this->assertTrue($item->isPending());

        // Returned item has return timestamp
        $this->assertEquals($returnedAt, $returnedItem->returnedAt());
        $this->assertTrue($returnedItem->isReturned());
        $this->assertFalse($returnedItem->isPending());
    }

    public function testLoanItemReturnWithoutExplicitTime(): void
    {
        $loanedAt = new DateTimeImmutable('2024-01-01 10:00:00 UTC');
        $beforeReturn = new DateTimeImmutable('now', new \DateTimeZone('UTC'));
        
        $item = LoanItem::create(
            'item-123', 'loan-456', 'book-789',
            'Clean Architecture', '9780134494166', 'Tecnología',
            $loanedAt
        );

        $returnedItem = $item->returnItem(); // No explicit time

        $this->assertTrue($returnedItem->isReturned());
        $this->assertNotNull($returnedItem->returnedAt());
        $this->assertGreaterThanOrEqual($beforeReturn, $returnedItem->returnedAt());
    }

    public function testCannotReturnAlreadyReturnedItem(): void
    {
        $loanedAt = new DateTimeImmutable('2024-01-01 10:00:00 UTC');
        
        $item = LoanItem::create(
            'item-123', 'loan-456', 'book-789',
            'Clean Architecture', '9780134494166', 'Tecnología',
            $loanedAt
        )->returnItem();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Item is already returned');

        $item->returnItem();
    }

    public function testOverdueDetection(): void
    {
        $loanedAt = new DateTimeImmutable('2024-01-01 10:00:00 UTC');
        $dueDate = $loanedAt->modify('+14 days'); // 2024-01-15 10:00:00 UTC
        
        $item = LoanItem::create(
            'item-123', 'loan-456', 'book-789',
            'Clean Architecture', '9780134494166', 'Tecnología',
            $loanedAt
        );

        // Before due date - not overdue
        $beforeDue = new DateTimeImmutable('2024-01-10 12:00:00 UTC');
        $this->assertFalse($item->isOverdue($beforeDue));
        $this->assertEquals(0, $item->daysOverdue($beforeDue));

        // At due date - not overdue
        $this->assertFalse($item->isOverdue($dueDate));
        $this->assertEquals(0, $item->daysOverdue($dueDate));

        // After due date - overdue
        $afterDue = new DateTimeImmutable('2024-01-20 12:00:00 UTC'); // 5 days after
        $this->assertTrue($item->isOverdue($afterDue));
        $this->assertEquals(5, $item->daysOverdue($afterDue));
    }

    public function testReturnedItemNotOverdue(): void
    {
        $loanedAt = new DateTimeImmutable('2024-01-01 10:00:00 UTC');
        $returnedAt = new DateTimeImmutable('2024-01-05 14:30:00 UTC');
        
        $item = LoanItem::create(
            'item-123', 'loan-456', 'book-789',
            'Clean Architecture', '9780134494166', 'Tecnología',
            $loanedAt
        )->returnItem($returnedAt);

        // Even after due date, returned item is not overdue
        $afterDue = new DateTimeImmutable('2024-01-20 12:00:00 UTC');
        $this->assertFalse($item->isOverdue($afterDue));
        $this->assertEquals(0, $item->daysOverdue($afterDue));
    }

    public function testLoanPeriod(): void
    {
        $loanedAt = new DateTimeImmutable('2024-01-01 10:00:00 UTC');
        
        $item = LoanItem::create(
            'item-123', 'loan-456', 'book-789',
            'Clean Architecture', '9780134494166', 'Tecnología',
            $loanedAt
        );

        $loanPeriod = $item->loanPeriod();
        
        $this->assertEquals($loanedAt, $loanPeriod->start());
        $this->assertEquals($item->dueAt(), $loanPeriod->end());
        $this->assertEquals(14, $loanPeriod->durationInDays());
    }

    public function testLoanItemEquality(): void
    {
        $loanedAt = new DateTimeImmutable('2024-01-01 10:00:00 UTC');
        
        $item1 = LoanItem::create(
            'item-123', 'loan-456', 'book-789',
            'Clean Architecture', '9780134494166', 'Tecnología',
            $loanedAt
        );
        
        $item2 = LoanItem::create(
            'item-123', 'loan-999', 'book-888', // Same ID, different data
            'Different Book', '9999999999999', 'Different Category',
            $loanedAt
        );
        
        $item3 = LoanItem::create(
            'item-999', 'loan-456', 'book-789', // Different ID, same data
            'Clean Architecture', '9780134494166', 'Tecnología',
            $loanedAt
        );

        $this->assertTrue($item1->equals($item2)); // Same ID
        $this->assertFalse($item1->equals($item3)); // Different ID
    }

    public function testFromPersistence(): void
    {
        $loanedAt = new DateTimeImmutable('2024-01-01 10:00:00 UTC');
        $dueAt = new DateTimeImmutable('2024-01-15 10:00:00 UTC');
        $returnedAt = new DateTimeImmutable('2024-01-10 14:30:00 UTC');
        
        $item = LoanItem::fromPersistence(
            id: 'item-123',
            loanId: 'loan-456',
            bookId: 'book-789',
            bookTitle: 'Clean Architecture',
            isbn: '9780134494166',
            categoryName: 'Tecnología',
            dueAt: $dueAt,
            returnedAt: $returnedAt
        );

        $this->assertEquals('item-123', $item->id());
        $this->assertEquals('loan-456', $item->loanId());
        $this->assertEquals('book-789', $item->bookId());
        $this->assertEquals('Clean Architecture', $item->bookTitle());
        $this->assertEquals('9780134494166', $item->isbn());
        $this->assertEquals('Tecnología', $item->categoryName());
        $this->assertEquals($dueAt, $item->dueAt());
        $this->assertEquals($returnedAt, $item->returnedAt());
        $this->assertTrue($item->isReturned());
    }
}