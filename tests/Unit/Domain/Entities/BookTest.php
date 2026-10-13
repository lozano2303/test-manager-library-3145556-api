<?php

namespace Tests\Unit\Domain\Entities;

use Library\Domain\Entities\Book;
use Library\Domain\ValueObjects\Isbn;
use Library\Domain\ValueObjects\Quantity;
use PHPUnit\Framework\TestCase;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Test for Book Entity
 * 
 * Evidence for T-04: nunca < 0, nunca > total
 * Tests Withdraw, Restock, and logical deletion operations
 */
class BookTest extends TestCase
{
    private function createValidBook(): Book
    {
        return Book::create(
            id: 'book-123',
            title: 'Clean Code',
            author: 'Robert C. Martin',
            isbn: Isbn::from('9780132350884'),
            categoryId: 'cat-123',
            totalCopies: Quantity::from(5)
        );
    }

    public function testBookCreation(): void
    {
        $book = $this->createValidBook();
        
        $this->assertEquals('book-123', $book->id());
        $this->assertEquals('Clean Code', $book->title());
        $this->assertEquals('Robert C. Martin', $book->author());
        $this->assertEquals('9780132350884', $book->isbn()->value());
        $this->assertEquals('cat-123', $book->categoryId());
        $this->assertEquals(5, $book->totalCopies()->value());
        $this->assertEquals(5, $book->availableCopies()->value()); // Starts equal to total
        $this->assertNull($book->coverKey());
        $this->assertNull($book->deletedAt());
        $this->assertTrue($book->isActive());
        $this->assertTrue($book->isAvailable());
    }

    public function testBookCreationWithCover(): void
    {
        $book = Book::create(
            id: 'book-123',
            title: 'Clean Code',
            author: 'Robert C. Martin',
            isbn: Isbn::from('9780132350884'),
            categoryId: 'cat-123',
            totalCopies: Quantity::from(3),
            coverKey: 'cover-key-abc123'
        );
        
        $this->assertEquals('cover-key-abc123', $book->coverKey());
        $this->assertTrue($book->hasCover());
    }

    public function testBookCreationRequiresAtLeastOneTotal(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Total copies must be at least 1');
        
        Book::create(
            id: 'book-123',
            title: 'Clean Code',
            author: 'Robert C. Martin',
            isbn: Isbn::from('9780132350884'),
            categoryId: 'cat-123',
            totalCopies: Quantity::zero() // Invalid - must be at least 1
        );
    }

    /**
     * T-04 Evidence: Test Withdraw operation - nunca < 0
     */
    public function testWithdrawReducesAvailableCopies(): void
    {
        $book = $this->createValidBook(); // 5 total, 5 available
        
        $bookAfterWithdraw = $book->withdraw(Quantity::from(2));
        
        $this->assertEquals(5, $book->totalCopies()->value()); // Original unchanged
        $this->assertEquals(5, $book->availableCopies()->value()); // Original unchanged
        $this->assertEquals(5, $bookAfterWithdraw->totalCopies()->value()); // Same total
        $this->assertEquals(3, $bookAfterWithdraw->availableCopies()->value()); // 5 - 2 = 3
    }

    /**
     * T-04 Evidence: nunca < 0 - Cannot withdraw more than available
     */
    public function testWithdrawCannotMakeAvailableNegative(): void
    {
        $book = $this->createValidBook(); // 5 available
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient copies. Available: 5, Requested: 6');
        
        $book->withdraw(Quantity::from(6)); // Cannot withdraw more than available
    }

    /**
     * T-04 Evidence: nunca < 0 - Cannot withdraw from empty book
     */
    public function testWithdrawFromEmptyBook(): void
    {
        $book = $this->createValidBook()
            ->withdraw(Quantity::from(5)); // All copies withdrawn
        
        $this->assertEquals(0, $book->availableCopies()->value());
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient copies. Available: 0, Requested: 1');
        
        $book->withdraw(Quantity::from(1)); // Cannot withdraw from empty
    }

    public function testWithdrawZeroIsInvalid(): void
    {
        $book = $this->createValidBook();
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Withdraw quantity must be positive');
        
        $book->withdraw(Quantity::zero());
    }

    /**
     * T-04 Evidence: Test Restock operation - nunca > total
     */
    public function testRestockIncreasesAvailableCopies(): void
    {
        $book = $this->createValidBook()
            ->withdraw(Quantity::from(3)); // 5 - 3 = 2 available
        
        $bookAfterRestock = $book->restock(Quantity::from(2)); // 2 + 2 = 4
        
        $this->assertEquals(2, $book->availableCopies()->value()); // Original unchanged
        $this->assertEquals(4, $bookAfterRestock->availableCopies()->value()); // Restocked
        $this->assertEquals(5, $bookAfterRestock->totalCopies()->value()); // Total unchanged
    }

    /**
     * T-04 Evidence: nunca > total - Cannot restock beyond total
     */
    public function testRestockCannotExceedTotal(): void
    {
        $book = $this->createValidBook()
            ->withdraw(Quantity::from(2)); // 3 available out of 5 total
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot exceed total copies. Total: 5, Would be: 6');
        
        $book->restock(Quantity::from(3)); // 3 + 3 = 6 > 5 total (invalid)
    }

    /**
     * T-04 Evidence: nunca > total - Full book cannot be restocked
     */
    public function testRestockFullBookIsInvalid(): void
    {
        $book = $this->createValidBook(); // 5 available out of 5 total (full)
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot exceed total copies. Total: 5, Would be: 6');
        
        $book->restock(Quantity::from(1)); // 5 + 1 = 6 > 5 total (invalid)
    }

    public function testRestockZeroIsInvalid(): void
    {
        $book = $this->createValidBook();
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Restock quantity must be positive');
        
        $book->restock(Quantity::zero());
    }

    /**
     * T-04 Evidence: Comprehensive constraint validation
     */
    public function testAvailableNeverExceedsTotal(): void
    {
        $book = Book::fromPersistence(
            id: 'book-123',
            title: 'Test Book',
            author: 'Test Author',
            isbn: Isbn::from('9780132350884'),
            categoryId: 'cat-123',
            totalCopies: Quantity::from(5),
            availableCopies: Quantity::from(6) // Invalid - exceeds total
        );
        
        // This should fail during construction
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Available copies (6) cannot exceed total copies (5)');
    }

    public function testUpdateTotalCopiesAdjustsAvailable(): void
    {
        $book = $this->createValidBook()
            ->withdraw(Quantity::from(2)); // 3 available out of 5 total
        
        // Increase total by 3, should increase available by 3
        $updatedBook = $book->updateTotalCopies(Quantity::from(8));
        
        $this->assertEquals(8, $updatedBook->totalCopies()->value());
        $this->assertEquals(6, $updatedBook->availableCopies()->value()); // 3 + 3 = 6
    }

    public function testUpdateTotalCopiesCannotMakeAvailableNegative(): void
    {
        $book = $this->createValidBook()
            ->withdraw(Quantity::from(2)); // 3 available out of 5 total
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot reduce total copies. Would make available negative: -1');
        
        // Reduce total by 4, would make available 3 - 4 = -1 (invalid)
        $book->updateTotalCopies(Quantity::from(1));
    }

    public function testUpdateTotalCopiesMustBeAtLeastOne(): void
    {
        $book = $this->createValidBook();
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Total copies must be at least 1');
        
        $book->updateTotalCopies(Quantity::zero());
    }

    public function testLogicalDeletion(): void
    {
        $book = $this->createValidBook();
        $deletedAt = new DateTimeImmutable('2024-01-15 10:00:00 UTC');
        
        $deletedBook = $book->delete($deletedAt);
        
        $this->assertTrue($book->isActive()); // Original unchanged
        $this->assertFalse($deletedBook->isActive()); // Deleted version
        $this->assertTrue($deletedBook->isDeleted());
        $this->assertEquals($deletedAt, $deletedBook->deletedAt());
        $this->assertFalse($deletedBook->isAvailable()); // Deleted books not available
    }

    public function testLogicalDeletionWithCurrentTime(): void
    {
        $book = $this->createValidBook();
        $beforeDelete = new DateTimeImmutable('now', new \DateTimeZone('UTC'));
        
        $deletedBook = $book->delete(); // No explicit time
        
        $this->assertTrue($deletedBook->isDeleted());
        $this->assertNotNull($deletedBook->deletedAt());
        $this->assertGreaterThanOrEqual($beforeDelete, $deletedBook->deletedAt());
    }

    public function testRestoreFromDeletion(): void
    {
        $book = $this->createValidBook()
            ->delete();
        
        $restoredBook = $book->restore();
        
        $this->assertTrue($book->isDeleted()); // Original unchanged
        $this->assertFalse($restoredBook->isDeleted()); // Restored version
        $this->assertTrue($restoredBook->isActive());
        $this->assertNull($restoredBook->deletedAt());
    }

    public function testUpdateMetadata(): void
    {
        $book = $this->createValidBook();
        
        $updatedBook = $book->updateMetadata(
            'New Title',
            'New Author',
            'new-cat-456'
        );
        
        $this->assertEquals('Clean Code', $book->title()); // Original unchanged
        $this->assertEquals('New Title', $updatedBook->title());
        $this->assertEquals('New Author', $updatedBook->author());
        $this->assertEquals('new-cat-456', $updatedBook->categoryId());
        $this->assertEquals($book->isbn()->value(), $updatedBook->isbn()->value()); // ISBN unchanged
        $this->assertEquals($book->totalCopies()->value(), $updatedBook->totalCopies()->value()); // Copies unchanged
    }

    public function testUpdateMetadataTrimsTitle(): void
    {
        $book = $this->createValidBook();
        
        $updatedBook = $book->updateMetadata('  Trimmed Title  ', 'Author', 'cat-123');
        
        $this->assertEquals('Trimmed Title', $updatedBook->title()); // Trimmed
    }

    public function testSetCover(): void
    {
        $book = $this->createValidBook();
        
        $bookWithCover = $book->setCover('new-cover-key');
        
        $this->assertNull($book->coverKey()); // Original unchanged
        $this->assertEquals('new-cover-key', $bookWithCover->coverKey());
        $this->assertTrue($bookWithCover->hasCover());
    }

    public function testSetCoverNull(): void
    {
        $book = Book::create(
            id: 'book-123',
            title: 'Test',
            author: 'Author',
            isbn: Isbn::from('9780132350884'),
            categoryId: 'cat-123',
            totalCopies: Quantity::from(1),
            coverKey: 'existing-cover'
        );
        
        $bookWithoutCover = $book->setCover(null);
        
        $this->assertEquals('existing-cover', $book->coverKey()); // Original unchanged
        $this->assertNull($bookWithoutCover->coverKey());
        $this->assertFalse($bookWithoutCover->hasCover());
    }

    public function testSetCoverEmptyStringBecomesNull(): void
    {
        $book = $this->createValidBook();
        
        $bookWithCover = $book->setCover(''); // Empty string
        
        $this->assertNull($bookWithCover->coverKey()); // Converted to null
        $this->assertFalse($bookWithCover->hasCover());
    }

    public function testCanWithdraw(): void
    {
        $book = $this->createValidBook(); // 5 available
        
        $this->assertTrue($book->canWithdraw(Quantity::from(1)));
        $this->assertTrue($book->canWithdraw(Quantity::from(5)));
        $this->assertFalse($book->canWithdraw(Quantity::from(6)));
    }

    public function testCanRestockChecksTotal(): void
    {
        $book = $this->createValidBook()
            ->withdraw(Quantity::from(2)); // 3 available, 5 total
        
        $this->assertTrue($book->canRestock(Quantity::from(1))); // 3 + 1 = 4 <= 5
        $this->assertTrue($book->canRestock(Quantity::from(2))); // 3 + 2 = 5 <= 5
        $this->assertFalse($book->canRestock(Quantity::from(3))); // 3 + 3 = 6 > 5
    }

    public function testDeletedBookNotAvailable(): void
    {
        $book = $this->createValidBook()
            ->delete();
        
        $this->assertFalse($book->isAvailable()); // Deleted books not available
        $this->assertFalse($book->canWithdraw(Quantity::from(1))); // Cannot withdraw from deleted
    }

    public function testBookEquality(): void
    {
        $book1 = $this->createValidBook();
        $book2 = Book::create(
            id: 'book-123', // Same ID
            title: 'Different Title',
            author: 'Different Author',
            isbn: Isbn::from('9780465026562'),
            categoryId: 'different-cat',
            totalCopies: Quantity::from(10)
        );
        $book3 = Book::create(
            id: 'book-456', // Different ID
            title: 'Clean Code',
            author: 'Robert C. Martin',
            isbn: Isbn::from('9780132350884'),
            categoryId: 'cat-123',
            totalCopies: Quantity::from(5)
        );
        
        $this->assertTrue($book1->equals($book2)); // Same ID
        $this->assertFalse($book1->equals($book3)); // Different ID
    }

    /**
     * Comprehensive test for T-04 evidence: nunca < 0, nunca > total
     */
    public function testT04EvidenceComprehensive(): void
    {
        $book = $this->createValidBook(); // 5 total, 5 available
        
        // Withdraw operations - ensuring never < 0
        $book = $book->withdraw(Quantity::from(2)); // 3 available
        $this->assertEquals(3, $book->availableCopies()->value());
        $this->assertGreaterThanOrEqual(0, $book->availableCopies()->value()); // Never < 0 ✓
        
        $book = $book->withdraw(Quantity::from(3)); // 0 available
        $this->assertEquals(0, $book->availableCopies()->value());
        $this->assertGreaterThanOrEqual(0, $book->availableCopies()->value()); // Never < 0 ✓
        
        // Restock operations - ensuring never > total
        $book = $book->restock(Quantity::from(2)); // 2 available
        $this->assertEquals(2, $book->availableCopies()->value());
        $this->assertLessThanOrEqual($book->totalCopies()->value(), $book->availableCopies()->value()); // Never > total ✓
        
        $book = $book->restock(Quantity::from(3)); // 5 available (full)
        $this->assertEquals(5, $book->availableCopies()->value());
        $this->assertEquals($book->totalCopies()->value(), $book->availableCopies()->value()); // Equal to total ✓
        $this->assertLessThanOrEqual($book->totalCopies()->value(), $book->availableCopies()->value()); // Never > total ✓
        
        // Verify constraints always hold
        $this->assertGreaterThanOrEqual(0, $book->availableCopies()->value()); // nunca < 0 ✓
        $this->assertLessThanOrEqual($book->totalCopies()->value(), $book->availableCopies()->value()); // nunca > total ✓
    }
}