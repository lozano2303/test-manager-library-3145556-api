<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Library\Domain\Entities\Book;
use Library\Domain\ValueObjects\Isbn;
use Library\Domain\ValueObjects\Quantity;
use InvalidArgumentException;

/**
 * Console command to verify T-04 Book Domain Entity evidence
 * 
 * Verifies:
 * - Withdraw operation (nunca < 0)
 * - Restock operation (nunca > total) 
 * - Logical deletion (baja lógica)
 */
class VerifyBookDomainCommand extends Command
{
    protected $signature = 'verify:book-domain';
    protected $description = 'Verify T-04 Book Domain Entity evidence: Withdraw, Restock, logical deletion';

    public function handle(): int
    {
        $this->info('🔍 Verifying T-04 Book Domain Entity Evidence...');
        $this->newLine();

        $allPassed = true;

        // Test 1: Withdraw operation - nunca < 0
        $this->info('1. Testing Withdraw operation (nunca < 0)');
        if ($this->verifyWithdrawOperation()) {
            $this->info('   ✅ Withdraw: available copies never go below 0 - PASSED');
        } else {
            $this->error('   ❌ Withdraw: available copies can go below 0 - FAILED');
            $allPassed = false;
        }

        // Test 2: Restock operation - nunca > total  
        $this->info('2. Testing Restock operation (nunca > total)');
        if ($this->verifyRestockOperation()) {
            $this->info('   ✅ Restock: available copies never exceed total - PASSED');
        } else {
            $this->error('   ❌ Restock: available copies can exceed total - FAILED');
            $allPassed = false;
        }

        // Test 3: Logical deletion
        $this->info('3. Testing Logical deletion (baja lógica)');
        if ($this->verifyLogicalDeletion()) {
            $this->info('   ✅ Logical deletion: books are soft deleted - PASSED');
        } else {
            $this->error('   ❌ Logical deletion: not working correctly - FAILED');
            $allPassed = false;
        }

        // Test 4: Copy constraints
        $this->info('4. Testing Copy constraints (0 <= available <= total)');
        if ($this->verifyCopyConstraints()) {
            $this->info('   ✅ Copy constraints: 0 <= available <= total always - PASSED');
        } else {
            $this->error('   ❌ Copy constraints: constraint violations possible - FAILED');
            $allPassed = false;
        }

        $this->newLine();

        if ($allPassed) {
            $this->info('🎉 T-04 Evidence VERIFIED: Book domain entity works correctly!');
            $this->info('   • Withdraw: never reduces available copies below 0');
            $this->info('   • Restock: never increases available copies above total');
            $this->info('   • Logical deletion: books are soft-deleted with timestamp');
            $this->info('   • Constraints: 0 ≤ available ≤ total always enforced');
            return self::SUCCESS;
        } else {
            $this->error('💥 T-04 Evidence FAILED: Some book domain operations are not working');
            return self::FAILURE;
        }
    }

    private function verifyWithdrawOperation(): bool
    {
        try {
            // Create a book with 5 copies
            $book = Book::create(
                id: 'test-book-1',
                title: 'Test Book',
                author: 'Test Author',
                isbn: Isbn::from('9780132350884'),
                categoryId: 'cat-123',
                totalCopies: Quantity::from(5)
            );

            // Withdraw some copies
            $book = $book->withdraw(Quantity::from(2));
            if ($book->availableCopies()->value() !== 3) {
                return false; // Should be 5 - 2 = 3
            }

            // Withdraw remaining copies
            $book = $book->withdraw(Quantity::from(3));
            if ($book->availableCopies()->value() !== 0) {
                return false; // Should be 3 - 3 = 0
            }

            // Try to withdraw when empty - should fail
            try {
                $book->withdraw(Quantity::from(1));
                return false; // Should have thrown exception
            } catch (InvalidArgumentException $e) {
                // Expected - this proves "nunca < 0"
            }

            // Try to withdraw more than available - should fail
            $bookWithSome = Book::create(
                id: 'test-book-2',
                title: 'Test Book 2',
                author: 'Test Author',
                isbn: Isbn::from('9780465026562'),
                categoryId: 'cat-123',
                totalCopies: Quantity::from(3)
            );

            try {
                $bookWithSome->withdraw(Quantity::from(4)); // More than available
                return false; // Should have thrown exception
            } catch (InvalidArgumentException $e) {
                // Expected - this proves "nunca < 0"
            }

            return true;

        } catch (\Throwable $e) {
            $this->error("   Error testing Withdraw: " . $e->getMessage());
            return false;
        }
    }

    private function verifyRestockOperation(): bool
    {
        try {
            // Create a book and withdraw some copies
            $book = Book::create(
                id: 'test-book-3',
                title: 'Test Book 3',
                author: 'Test Author',
                isbn: Isbn::from('9780307474728'),
                categoryId: 'cat-123',
                totalCopies: Quantity::from(5)
            )->withdraw(Quantity::from(3)); // 2 available out of 5 total

            // Restock some copies
            $book = $book->restock(Quantity::from(2)); // 2 + 2 = 4
            if ($book->availableCopies()->value() !== 4) {
                return false;
            }

            // Restock to full
            $book = $book->restock(Quantity::from(1)); // 4 + 1 = 5 (full)
            if ($book->availableCopies()->value() !== 5) {
                return false;
            }
            if (!$book->availableCopies()->equals($book->totalCopies())) {
                return false; // Should be equal to total
            }

            // Try to restock beyond total - should fail
            try {
                $book->restock(Quantity::from(1)); // 5 + 1 = 6 > 5 total
                return false; // Should have thrown exception
            } catch (InvalidArgumentException $e) {
                // Expected - this proves "nunca > total"
            }

            // Try to restock a partially full book beyond total
            $partialBook = Book::create(
                id: 'test-book-4',
                title: 'Test Book 4',
                author: 'Test Author',
                isbn: Isbn::from('9780201633610'),
                categoryId: 'cat-123',
                totalCopies: Quantity::from(3)
            )->withdraw(Quantity::from(1)); // 2 available out of 3 total

            try {
                $partialBook->restock(Quantity::from(2)); // 2 + 2 = 4 > 3 total
                return false; // Should have thrown exception
            } catch (InvalidArgumentException $e) {
                // Expected - this proves "nunca > total"
            }

            return true;

        } catch (\Throwable $e) {
            $this->error("   Error testing Restock: " . $e->getMessage());
            return false;
        }
    }

    private function verifyLogicalDeletion(): bool
    {
        try {
            // Create a book
            $book = Book::create(
                id: 'test-book-5',
                title: 'Test Book 5',
                author: 'Test Author',
                isbn: Isbn::from('9780201835953'),
                categoryId: 'cat-123',
                totalCopies: Quantity::from(2)
            );

            if (!$book->isActive()) {
                return false; // Should be active initially
            }
            if ($book->isDeleted()) {
                return false; // Should not be deleted initially
            }

            // Delete the book (logical deletion)
            $deletedBook = $book->delete();
            
            if ($book->isDeleted()) {
                return false; // Original should be unchanged
            }
            if (!$deletedBook->isDeleted()) {
                return false; // New instance should be deleted
            }
            if ($deletedBook->isActive()) {
                return false; // Deleted books not active
            }
            if ($deletedBook->isAvailable()) {
                return false; // Deleted books not available
            }
            if ($deletedBook->deletedAt() === null) {
                return false; // Should have deletion timestamp
            }

            // Restore the book
            $restoredBook = $deletedBook->restore();
            
            if (!$restoredBook->isActive()) {
                return false; // Should be active after restore
            }
            if ($restoredBook->isDeleted()) {
                return false; // Should not be deleted after restore
            }
            if ($restoredBook->deletedAt() !== null) {
                return false; // Should not have deletion timestamp after restore
            }

            return true;

        } catch (\Throwable $e) {
            $this->error("   Error testing Logical Deletion: " . $e->getMessage());
            return false;
        }
    }

    private function verifyCopyConstraints(): bool
    {
        try {
            // Test constraint: available <= total always
            $book = Book::create(
                id: 'test-book-6',
                title: 'Test Book 6',
                author: 'Test Author',
                isbn: Isbn::from('9780134685991'),
                categoryId: 'cat-123',
                totalCopies: Quantity::from(4)
            );

            // Initial state: available should equal total
            if (!$book->availableCopies()->equals($book->totalCopies())) {
                return false;
            }

            // After withdraw: available <= total
            $book = $book->withdraw(Quantity::from(2));
            if ($book->availableCopies()->greaterThan($book->totalCopies())) {
                return false;
            }

            // After restock: available <= total
            $book = $book->restock(Quantity::from(1));
            if ($book->availableCopies()->greaterThan($book->totalCopies())) {
                return false;
            }

            // Test constraint: available >= 0 always
            if ($book->availableCopies()->value() < 0) {
                return false;
            }

            // Test constraint: total >= 1 always
            if ($book->totalCopies()->value() < 1) {
                return false;
            }

            // Test invalid construction should fail
            try {
                Book::fromPersistence(
                    id: 'invalid-book',
                    title: 'Invalid',
                    author: 'Author',
                    isbn: Isbn::from('9780132350884'),
                    categoryId: 'cat-123',
                    totalCopies: Quantity::from(3),
                    availableCopies: Quantity::from(4) // 4 > 3 (invalid)
                );
                return false; // Should have thrown exception
            } catch (InvalidArgumentException $e) {
                // Expected - constraints enforced
            }

            return true;

        } catch (\Throwable $e) {
            $this->error("   Error testing Copy Constraints: " . $e->getMessage());
            return false;
        }
    }
}