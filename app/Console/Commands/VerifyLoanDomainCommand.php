<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Library\Domain\Entities\Loan;
use Library\Domain\Entities\LoanItem;
use Library\Domain\Services\Clock;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Fake Clock for testing (inline implementation for command)
 */
class FakeClockForCommand implements Clock
{
    public function __construct(private DateTimeImmutable $fixedTime) {}

    public function now(): DateTimeImmutable
    {
        return $this->fixedTime;
    }
}

/**
 * Console command to verify T-05 Loan Domain Entity evidence
 * 
 * Verifies:
 * - AddItem operation (máx. 3)
 * - ReturnItem operation  
 * - Calculated states with Clock falso: open, overdue, closed
 * - 14 days loan period
 */
class VerifyLoanDomainCommand extends Command
{
    protected $signature = 'verify:loan-domain';
    protected $description = 'Verify T-05 Loan Domain Entity evidence: AddItem, ReturnItem, calculated states with fake Clock';

    public function handle(): int
    {
        $this->info('🔍 Verifying T-05 Loan Domain Entity Evidence...');
        $this->newLine();

        $allPassed = true;

        // Test 1: AddItem operation and max 3 items
        $this->info('1. Testing AddItem operation (máx. 3)');
        if ($this->verifyAddItemOperation()) {
            $this->info('   ✅ AddItem: can add up to 3 items, rejects 4th - PASSED');
        } else {
            $this->error('   ❌ AddItem: max 3 items constraint not working - FAILED');
            $allPassed = false;
        }

        // Test 2: ReturnItem operation
        $this->info('2. Testing ReturnItem operation');
        if ($this->verifyReturnItemOperation()) {
            $this->info('   ✅ ReturnItem: can return items, prevents double return - PASSED');
        } else {
            $this->error('   ❌ ReturnItem: return operations not working correctly - FAILED');
            $allPassed = false;
        }

        // Test 3: 14 days loan period
        $this->info('3. Testing 14 días loan period');
        if ($this->verify14DaysPeriod()) {
            $this->info('   ✅ 14 días: items due exactly 14 days after loan - PASSED');
        } else {
            $this->error('   ❌ 14 días: loan period not 14 days - FAILED');
            $allPassed = false;
        }

        // Test 4: Calculated states with fake Clock
        $this->info('4. Testing calculated states with Clock falso');
        if ($this->verifyCalculatedStates()) {
            $this->info('   ✅ Clock falso: open, overdue, closed states work correctly - PASSED');
        } else {
            $this->error('   ❌ Clock falso: calculated states not working - FAILED');
            $allPassed = false;
        }

        $this->newLine();

        if ($allPassed) {
            $this->info('🎉 T-05 Evidence VERIFIED: Loan domain entity works correctly!');
            $this->info('   • AddItem: can add up to 3 items maximum (máx. 3)');
            $this->info('   • ReturnItem: can return items, prevents duplicates');
            $this->info('   • 14 días: loan period is exactly 14 days');
            $this->info('   • Clock falso: states calculated with fake time (open/overdue/closed)');
            return self::SUCCESS;
        } else {
            $this->error('💥 T-05 Evidence FAILED: Some loan domain operations are not working');
            return self::FAILURE;
        }
    }

    private function verifyAddItemOperation(): bool
    {
        try {
            $loanedAt = new DateTimeImmutable('2024-01-01 10:00:00 UTC');
            $loan = Loan::create(
                id: 'test-loan-1',
                memberId: 'member-123',
                loanedByUserId: 'user-456',
                loanedAt: $loanedAt
            );

            // Add first item
            $loan = $loan->addItem('item-1', 'book-1', 'Book 1', '1111111111111', 'Cat 1');
            if ($loan->itemCount() !== 1) {
                return false;
            }

            // Add second item
            $loan = $loan->addItem('item-2', 'book-2', 'Book 2', '2222222222222', 'Cat 2');
            if ($loan->itemCount() !== 2) {
                return false;
            }

            // Add third item (maximum)
            $loan = $loan->addItem('item-3', 'book-3', 'Book 3', '3333333333333', 'Cat 3');
            if ($loan->itemCount() !== 3) {
                return false;
            }

            if ($loan->canAddMoreItems()) {
                return false; // Should be at maximum
            }

            // Try to add fourth item - should fail
            try {
                $loan->addItem('item-4', 'book-4', 'Book 4', '4444444444444', 'Cat 4');
                return false; // Should have thrown exception
            } catch (InvalidArgumentException $e) {
                // Expected - proves máx. 3 constraint
            }

            // Test duplicate book prevention
            $loanWithOne = Loan::create('loan-2', 'member-123', 'user-456', $loanedAt)
                ->addItem('item-1', 'book-1', 'Book 1', '1111111111111', 'Cat 1');

            try {
                $loanWithOne->addItem('item-2', 'book-1', 'Same Book', '1111111111111', 'Cat 1'); // Same book ID
                return false; // Should have thrown exception
            } catch (InvalidArgumentException $e) {
                // Expected - proves duplicate book prevention
            }

            return true;

        } catch (\Throwable $e) {
            $this->error("   Error testing AddItem: " . $e->getMessage());
            return false;
        }
    }

    private function verifyReturnItemOperation(): bool
    {
        try {
            $loanedAt = new DateTimeImmutable('2024-01-01 10:00:00 UTC');
            $loan = Loan::create('test-loan-2', 'member-123', 'user-456', $loanedAt)
                ->addItem('item-1', 'book-1', 'Book 1', '1111111111111', 'Cat 1')
                ->addItem('item-2', 'book-2', 'Book 2', '2222222222222', 'Cat 2');

            if ($loan->pendingItemCount() !== 2) {
                return false;
            }

            // Return first item
            $returnedAt = new DateTimeImmutable('2024-01-05 14:30:00 UTC');
            $loan = $loan->returnItem('item-1', $returnedAt);

            if ($loan->pendingItemCount() !== 1) {
                return false;
            }
            if (count($loan->returnedItems()) !== 1) {
                return false;
            }

            $returnedItem = $loan->getItem('item-1');
            if ($returnedItem->returnedAt() !== $returnedAt) {
                return false;
            }

            // Return second item
            $loan = $loan->returnItem('item-2');

            if (!$loan->isFullyReturned()) {
                return false;
            }
            if ($loan->pendingItemCount() !== 0) {
                return false;
            }

            // Try to return already returned item - should fail
            try {
                $loan->returnItem('item-1');
                return false; // Should have thrown exception
            } catch (InvalidArgumentException $e) {
                // Expected - prevents double return
            }

            return true;

        } catch (\Throwable $e) {
            $this->error("   Error testing ReturnItem: " . $e->getMessage());
            return false;
        }
    }

    private function verify14DaysPeriod(): bool
    {
        try {
            $loanedAt = new DateTimeImmutable('2024-01-01 10:00:00 UTC');
            $loan = Loan::create('test-loan-3', 'member-123', 'user-456', $loanedAt)
                ->addItem('item-1', 'book-1', 'Book 1', '1111111111111', 'Cat 1');

            $item = $loan->items()[0];
            $expectedDueDate = $loanedAt->modify('+14 days'); // 2024-01-15 10:00:00 UTC

            if (!$item->dueAt()->equals($expectedDueDate)) {
                return false;
            }

            // Test loan period calculation
            $loanPeriod = $item->loanPeriod();
            if ($loanPeriod->durationInDays() !== 14) {
                return false;
            }

            if (!$loanPeriod->start()->equals($loanedAt)) {
                return false;
            }

            if (!$loanPeriod->end()->equals($expectedDueDate)) {
                return false;
            }

            return true;

        } catch (\Throwable $e) {
            $this->error("   Error testing 14 days period: " . $e->getMessage());
            return false;
        }
    }

    private function verifyCalculatedStates(): bool
    {
        try {
            $loanedAt = new DateTimeImmutable('2024-01-01 10:00:00 UTC');
            $loan = Loan::create('test-loan-4', 'member-123', 'user-456', $loanedAt)
                ->addItem('item-1', 'book-1', 'Book 1', '1111111111111', 'Cat 1')
                ->addItem('item-2', 'book-2', 'Book 2', '2222222222222', 'Cat 2');

            // Test 'open' state with fake clock BEFORE due date
            $clockBeforeDue = new FakeClockForCommand(new DateTimeImmutable('2024-01-10 12:00:00 UTC'));
            $state = $loan->calculateState($clockBeforeDue);
            
            if ($state !== 'open') {
                return false; // Should be open before due date
            }

            if ($loan->hasOverdueItems($clockBeforeDue)) {
                return false; // Should not have overdue items yet
            }

            // Test 'overdue' state with fake clock AFTER due date  
            $clockAfterDue = new FakeClockForCommand(new DateTimeImmutable('2024-01-20 12:00:00 UTC'));
            $state = $loan->calculateState($clockAfterDue);

            if ($state !== 'overdue') {
                return false; // Should be overdue after due date
            }

            if (!$loan->hasOverdueItems($clockAfterDue)) {
                return false; // Should have overdue items
            }

            if (count($loan->overdueItems($clockAfterDue)) !== 2) {
                return false; // Both items should be overdue
            }

            // Return all items
            $loanFullyReturned = $loan->returnItem('item-1')->returnItem('item-2');

            // Test 'closed' state with fake clock (any time)
            $state = $loanFullyReturned->calculateState($clockAfterDue);

            if ($state !== 'closed') {
                return false; // Should be closed when all returned
            }

            if (!$loanFullyReturned->isFullyReturned()) {
                return false; // Should be fully returned
            }

            // Test state transition over time
            $loan2 = Loan::create('test-loan-5', 'member-123', 'user-456', $loanedAt)
                ->addItem('item-1', 'book-1', 'Book 1', '1111111111111', 'Cat 1');

            // Day 1: open
            $day1Clock = new FakeClockForCommand(new DateTimeImmutable('2024-01-01 12:00:00 UTC'));
            if ($loan2->calculateState($day1Clock) !== 'open') {
                return false;
            }

            // Day 10: still open
            $day10Clock = new FakeClockForCommand(new DateTimeImmutable('2024-01-10 12:00:00 UTC'));
            if ($loan2->calculateState($day10Clock) !== 'open') {
                return false;
            }

            // Day 16: overdue (1 day past due)
            $day16Clock = new FakeClockForCommand(new DateTimeImmutable('2024-01-16 10:00:01 UTC'));
            if ($loan2->calculateState($day16Clock) !== 'overdue') {
                return false;
            }

            return true;

        } catch (\Throwable $e) {
            $this->error("   Error testing calculated states: " . $e->getMessage());
            return false;
        }
    }
}