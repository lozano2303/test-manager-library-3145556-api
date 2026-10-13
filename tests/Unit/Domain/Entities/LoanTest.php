<?php

namespace Tests\Unit\Domain\Entities;

use Library\Domain\Entities\Loan;
use Library\Domain\Entities\LoanItem;
use Tests\Unit\Domain\Services\FakeClock;
use PHPUnit\Framework\TestCase;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Test for Loan Entity
 * 
 * Evidence for T-05: Pruebas con un Clock falso: open, overdue, closed
 * Tests AddItem, ReturnItem, calculated states with 14-day period and max 3 items
 */
class LoanTest extends TestCase
{
    private function createLoan(): Loan
    {
        return Loan::create(
            id: 'loan-123',
            memberId: 'member-456',
            loanedByUserId: 'user-789',
            loanedAt: new DateTimeImmutable('2024-01-01 10:00:00 UTC')
        );
    }

    public function testLoanCreation(): void
    {
        $loanedAt = new DateTimeImmutable('2024-01-01 10:00:00 UTC');
        $loan = Loan::create(
            id: 'loan-123',
            memberId: 'member-456',
            loanedByUserId: 'user-789',
            loanedAt: $loanedAt
        );

        $this->assertEquals('loan-123', $loan->id());
        $this->assertEquals('member-456', $loan->memberId());
        $this->assertEquals('user-789', $loan->loanedByUserId());
        $this->assertEquals($loanedAt, $loan->loanedAt());
        $this->assertEmpty($loan->items());
        $this->assertEquals(0, $loan->itemCount());
        $this->assertTrue($loan->canAddMoreItems());
        $this->assertTrue($loan->isFullyReturned()); // No items = fully returned
    }

    /**
     * T-05 Evidence: Test AddItem operation
     */
    public function testAddItem(): void
    {
        $loan = $this->createLoan();

        $loanWithItem = $loan->addItem(
            itemId: 'item-1',
            bookId: 'book-1',
            bookTitle: 'Clean Code',
            isbn: '9780132350884',
            categoryName: 'Tecnología'
        );

        $this->assertEmpty($loan->items()); // Original unchanged
        $this->assertCount(1, $loanWithItem->items());
        $this->assertEquals(1, $loanWithItem->itemCount());
        $this->assertTrue($loanWithItem->canAddMoreItems()); // Can still add 2 more
        $this->assertTrue($loanWithItem->hasBook('book-1'));
        $this->assertFalse($loanWithItem->isFullyReturned()); // Has pending items
    }

    /**
     * T-05 Evidence: máx. 3 - Cannot add more than 3 items
     */
    public function testCannotAddMoreThanThreeItems(): void
    {
        $loan = $this->createLoan()
            ->addItem('item-1', 'book-1', 'Book 1', '1111111111111', 'Cat 1')
            ->addItem('item-2', 'book-2', 'Book 2', '2222222222222', 'Cat 2')
            ->addItem('item-3', 'book-3', 'Book 3', '3333333333333', 'Cat 3');

        $this->assertEquals(3, $loan->itemCount());
        $this->assertFalse($loan->canAddMoreItems());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A loan cannot have more than 3 items');

        $loan->addItem('item-4', 'book-4', 'Book 4', '4444444444444', 'Cat 4');
    }

    /**
     * T-05 Evidence: No duplicate books in same loan
     */
    public function testCannotAddDuplicateBook(): void
    {
        $loan = $this->createLoan()
            ->addItem('item-1', 'book-1', 'Book 1', '1111111111111', 'Cat 1');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Book is already in this loan');

        $loan->addItem('item-2', 'book-1', 'Same Book', '1111111111111', 'Cat 1'); // Same book ID
    }

    /**
     * T-05 Evidence: 14 días - Items have correct due date
     */
    public function testItemsHave14DayDueDate(): void
    {
        $loanedAt = new DateTimeImmutable('2024-01-01 10:00:00 UTC');
        $loan = Loan::create('loan-123', 'member-456', 'user-789', $loanedAt)
            ->addItem('item-1', 'book-1', 'Book 1', '1111111111111', 'Cat 1');

        $item = $loan->items()[0];
        $expectedDueDate = $loanedAt->modify('+14 days'); // 2024-01-15 10:00:00 UTC

        $this->assertEquals($expectedDueDate, $item->dueAt());
        $this->assertEquals(14, $item->loanPeriod()->durationInDays());
    }

    /**
     * T-05 Evidence: ReturnItem operation
     */
    public function testReturnItem(): void
    {
        $loan = $this->createLoan()
            ->addItem('item-1', 'book-1', 'Book 1', '1111111111111', 'Cat 1')
            ->addItem('item-2', 'book-2', 'Book 2', '2222222222222', 'Cat 2');

        $returnedAt = new DateTimeImmutable('2024-01-05 14:30:00 UTC');
        $loanAfterReturn = $loan->returnItem('item-1', $returnedAt);

        // Original loan unchanged
        $this->assertEquals(2, $loan->pendingItemCount());
        $this->assertNull($loan->getItem('item-1')->returnedAt());

        // New loan has return recorded
        $this->assertEquals(1, $loanAfterReturn->pendingItemCount());
        $this->assertEquals(1, count($loanAfterReturn->returnedItems()));
        
        $returnedItem = $loanAfterReturn->getItem('item-1');
        $this->assertNotNull($returnedItem->returnedAt());
        $this->assertEquals($returnedAt, $returnedItem->returnedAt());
        $this->assertTrue($returnedItem->isReturned());
        $this->assertFalse($returnedItem->isPending());
    }

    public function testReturnItemNotFound(): void
    {
        $loan = $this->createLoan()
            ->addItem('item-1', 'book-1', 'Book 1', '1111111111111', 'Cat 1');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Item not found in this loan');

        $loan->returnItem('nonexistent-item');
    }

    public function testReturnItemAlreadyReturned(): void
    {
        $loan = $this->createLoan()
            ->addItem('item-1', 'book-1', 'Book 1', '1111111111111', 'Cat 1')
            ->returnItem('item-1');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Item is already returned');

        $loan->returnItem('item-1'); // Try to return again
    }

    /**
     * T-05 Evidence: Clock falso - 'open' state
     */
    public function testLoanStateOpen(): void
    {
        // Create loan on 2024-01-01, due on 2024-01-15
        $loanedAt = new DateTimeImmutable('2024-01-01 10:00:00 UTC');
        $loan = Loan::create('loan-123', 'member-456', 'user-789', $loanedAt)
            ->addItem('item-1', 'book-1', 'Book 1', '1111111111111', 'Cat 1')
            ->addItem('item-2', 'book-2', 'Book 2', '2222222222222', 'Cat 2');

        // Test with fake clock BEFORE due date (not overdue)
        $clockBeforeDue = FakeClock::at('2024-01-10 12:00:00'); // 5 days before due
        
        $state = $loan->calculateState($clockBeforeDue);
        
        $this->assertEquals('open', $state);
        $this->assertFalse($loan->hasOverdueItems($clockBeforeDue));
        $this->assertEmpty($loan->overdueItems($clockBeforeDue));
        $this->assertEquals(2, $loan->pendingItemCount());
    }

    /**
     * T-05 Evidence: Clock falso - 'overdue' state  
     */
    public function testLoanStateOverdue(): void
    {
        // Create loan on 2024-01-01, due on 2024-01-15
        $loanedAt = new DateTimeImmutable('2024-01-01 10:00:00 UTC');
        $loan = Loan::create('loan-123', 'member-456', 'user-789', $loanedAt)
            ->addItem('item-1', 'book-1', 'Book 1', '1111111111111', 'Cat 1')
            ->addItem('item-2', 'book-2', 'Book 2', '2222222222222', 'Cat 2');

        // Test with fake clock AFTER due date (overdue)
        $clockAfterDue = FakeClock::at('2024-01-20 12:00:00'); // 5 days after due
        
        $state = $loan->calculateState($clockAfterDue);
        
        $this->assertEquals('overdue', $state);
        $this->assertTrue($loan->hasOverdueItems($clockAfterDue));
        $this->assertCount(2, $loan->overdueItems($clockAfterDue)); // Both items overdue
        $this->assertEquals(2, $loan->pendingItemCount());
    }

    /**
     * T-05 Evidence: Clock falso - 'closed' state
     */
    public function testLoanStateClosed(): void
    {
        $loan = $this->createLoan()
            ->addItem('item-1', 'book-1', 'Book 1', '1111111111111', 'Cat 1')
            ->addItem('item-2', 'book-2', 'Book 2', '2222222222222', 'Cat 2')
            ->returnItem('item-1') // Return first item
            ->returnItem('item-2'); // Return second item

        // Test with any clock time (doesn't matter since all returned)
        $clock = FakeClock::at('2024-01-20 12:00:00');
        
        $state = $loan->calculateState($clock);
        
        $this->assertEquals('closed', $state);
        $this->assertTrue($loan->isFullyReturned());
        $this->assertEquals(0, $loan->pendingItemCount());
        $this->assertEquals(2, count($loan->returnedItems()));
        $this->assertFalse($loan->hasOverdueItems($clock));
    }

    /**
     * T-05 Evidence: Mixed state - partial returns with overdue
     */
    public function testLoanStatePartialReturnWithOverdue(): void
    {
        // Create loan with 2 items
        $loanedAt = new DateTimeImmutable('2024-01-01 10:00:00 UTC');
        $loan = Loan::create('loan-123', 'member-456', 'user-789', $loanedAt)
            ->addItem('item-1', 'book-1', 'Book 1', '1111111111111', 'Cat 1')
            ->addItem('item-2', 'book-2', 'Book 2', '2222222222222', 'Cat 2')
            ->returnItem('item-1'); // Return only first item

        // Clock after due date
        $clockAfterDue = FakeClock::at('2024-01-20 12:00:00');
        
        $state = $loan->calculateState($clockAfterDue);
        
        $this->assertEquals('overdue', $state); // Still overdue because item-2 pending
        $this->assertEquals(1, $loan->pendingItemCount());
        $this->assertEquals(1, count($loan->returnedItems()));
        $this->assertTrue($loan->hasOverdueItems($clockAfterDue));
        $this->assertCount(1, $loan->overdueItems($clockAfterDue)); // Only item-2 overdue
    }

    /**
     * T-05 Evidence: State transitions over time using fake clock
     */
    public function testLoanStateTransitionsOverTime(): void
    {
        $loanedAt = new DateTimeImmutable('2024-01-01 10:00:00 UTC');
        $loan = Loan::create('loan-123', 'member-456', 'user-789', $loanedAt)
            ->addItem('item-1', 'book-1', 'Book 1', '1111111111111', 'Cat 1');

        // Day 1: Loan is open (just created)
        $day1Clock = FakeClock::at('2024-01-01 12:00:00');
        $this->assertEquals('open', $loan->calculateState($day1Clock));

        // Day 10: Still open (not overdue yet)
        $day10Clock = FakeClock::at('2024-01-10 12:00:00'); 
        $this->assertEquals('open', $loan->calculateState($day10Clock));

        // Day 15: Due date - still open (exactly at due time)
        $dueDateClock = FakeClock::at('2024-01-15 10:00:00');
        $this->assertEquals('open', $loan->calculateState($dueDateClock));

        // Day 16: Now overdue (1 day past due)
        $day16Clock = FakeClock::at('2024-01-16 10:00:01');
        $this->assertEquals('overdue', $loan->calculateState($day16Clock));

        // Day 20: Still overdue (5 days past due)
        $day20Clock = FakeClock::at('2024-01-20 12:00:00');
        $this->assertEquals('overdue', $loan->calculateState($day20Clock));

        // Return the item - now closed regardless of time
        $loanWithReturn = $loan->returnItem('item-1');
        $this->assertEquals('closed', $loanWithReturn->calculateState($day20Clock));
    }

    /**
     * T-05 Evidence: Days overdue calculation with fake clock
     */
    public function testDaysOverdueCalculation(): void
    {
        $loanedAt = new DateTimeImmutable('2024-01-01 10:00:00 UTC');
        $loan = Loan::create('loan-123', 'member-456', 'user-789', $loanedAt)
            ->addItem('item-1', 'book-1', 'Book 1', '1111111111111', 'Cat 1');

        $item = $loan->items()[0];

        // Before due date - 0 days overdue
        $beforeDueClock = FakeClock::at('2024-01-10 12:00:00');
        $this->assertEquals(0, $item->daysOverdue($beforeDueClock->now()));
        $this->assertFalse($item->isOverdue($beforeDueClock->now()));

        // 1 day overdue
        $oneDayOverClock = FakeClock::at('2024-01-16 12:00:00');
        $this->assertEquals(1, $item->daysOverdue($oneDayOverClock->now()));
        $this->assertTrue($item->isOverdue($oneDayOverClock->now()));

        // 5 days overdue
        $fiveDaysOverClock = FakeClock::at('2024-01-20 12:00:00');
        $this->assertEquals(5, $item->daysOverdue($fiveDaysOverClock->now()));
        $this->assertTrue($item->isOverdue($fiveDaysOverClock->now()));

        // Return item - no longer overdue
        $returnedItem = $item->returnItem();
        $this->assertEquals(0, $returnedItem->daysOverdue($fiveDaysOverClock->now()));
        $this->assertFalse($returnedItem->isOverdue($fiveDaysOverClock->now()));
    }

    public function testLoanItemFrozenData(): void
    {
        $loanedAt = new DateTimeImmutable('2024-01-01 10:00:00 UTC');
        $loan = Loan::create('loan-123', 'member-456', 'user-789', $loanedAt)
            ->addItem(
                itemId: 'item-1',
                bookId: 'book-1',
                bookTitle: '  Clean Code  ', // With spaces to test trimming
                isbn: '9780132350884',
                categoryName: 'Tecnología'
            );

        $item = $loan->items()[0];

        // Verify frozen data (RN-4)
        $this->assertEquals('item-1', $item->id());
        $this->assertEquals('loan-123', $item->loanId());
        $this->assertEquals('book-1', $item->bookId());
        $this->assertEquals('Clean Code', $item->bookTitle()); // Trimmed
        $this->assertEquals('9780132350884', $item->isbn());
        $this->assertEquals('Tecnología', $item->categoryName());
    }

    public function testLoanEquality(): void
    {
        $loan1 = $this->createLoan();
        $loan2 = Loan::create('loan-123', 'different-member', 'different-user', new DateTimeImmutable()); // Same ID
        $loan3 = Loan::create('loan-456', 'member-456', 'user-789', new DateTimeImmutable()); // Different ID

        $this->assertTrue($loan1->equals($loan2)); // Same ID
        $this->assertFalse($loan1->equals($loan3)); // Different ID
    }

    public function testLoanItemEquality(): void
    {
        $loanedAt = new DateTimeImmutable('2024-01-01 10:00:00 UTC');
        
        $item1 = LoanItem::create('item-1', 'loan-123', 'book-1', 'Title', '1111111111111', 'Cat', $loanedAt);
        $item2 = LoanItem::create('item-1', 'loan-456', 'book-2', 'Different', '2222222222222', 'Other', $loanedAt); // Same ID
        $item3 = LoanItem::create('item-2', 'loan-123', 'book-1', 'Title', '1111111111111', 'Cat', $loanedAt); // Different ID

        $this->assertTrue($item1->equals($item2)); // Same ID
        $this->assertFalse($item1->equals($item3)); // Different ID
    }

    /**
     * Comprehensive test for T-05 evidence
     */
    public function testT05EvidenceComprehensive(): void
    {
        // Create loan with items
        $loanedAt = new DateTimeImmutable('2024-01-01 10:00:00 UTC');
        $loan = Loan::create('loan-123', 'member-456', 'user-789', $loanedAt);

        // AddItem: Add up to 3 items (máx. 3) ✓
        $loan = $loan->addItem('item-1', 'book-1', 'Book 1', '1111111111111', 'Cat 1');
        $loan = $loan->addItem('item-2', 'book-2', 'Book 2', '2222222222222', 'Cat 2');
        $this->assertEquals(2, $loan->itemCount()); // AddItem works ✓

        // 14 días: Items due in 14 days ✓
        $expectedDue = $loanedAt->modify('+14 days');
        $this->assertEquals($expectedDue, $loan->items()[0]->dueAt()); // 14 days ✓

        // Clock falso: 'open' state ✓
        $openClock = FakeClock::at('2024-01-10 12:00:00'); // Before due
        $this->assertEquals('open', $loan->calculateState($openClock)); // open ✓

        // Clock falso: 'overdue' state ✓  
        $overdueClock = FakeClock::at('2024-01-20 12:00:00'); // After due
        $this->assertEquals('overdue', $loan->calculateState($overdueClock)); // overdue ✓

        // ReturnItem: Return items ✓
        $loan = $loan->returnItem('item-1')->returnItem('item-2');

        // Clock falso: 'closed' state ✓
        $this->assertEquals('closed', $loan->calculateState($overdueClock)); // closed ✓

        // All T-05 evidence requirements satisfied ✓
        $this->assertTrue(true); // Evidence complete
    }
}