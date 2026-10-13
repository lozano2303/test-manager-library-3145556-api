<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Verification Command for T-08: Book Services
 * 
 * Verifies HU-03 and HU-04 work against fakes
 */
class VerifyBookServicesCommand extends Command
{
    protected $signature = 'verify:book-services';
    protected $description = 'Verify T-08: Book services with fakes';

    public function handle(): int
    {
        $this->info('=== T-08 Book Services Verification ===');
        
        try {
            // Test HU-03: Manage Books
            $this->verifyManageBooks();
            
            // Test HU-04: Search Books
            $this->verifySearchBooks();
            
            $this->info('✅ T-08 Book Services: All verifications passed');
            return 0;
            
        } catch (\Exception $e) {
            $this->error('❌ T-08 Book Services: Verification failed');
            $this->error($e->getMessage());
            return 1;
        }
    }

    private function verifyManageBooks(): void
    {
        $this->info('Testing HU-03: Manage Books...');
        
        // Setup fakes
        $bookRepo = new \Tests\Unit\Application\Fakes\FakeBookRepository();
        $coverStorage = new \Tests\Unit\Application\Fakes\FakeCoverStorage();
        
        $createService = new \Library\Application\Services\CreateBookService($bookRepo, $coverStorage);
        $editService = new \Library\Application\Services\EditBookService($bookRepo, $coverStorage);
        $deleteService = new \Library\Application\Services\DeleteBookService($bookRepo);
        
        // Test book creation
        $book = $createService->createBook(
            'Test Book',
            'Test Author',
            '1234567890123',
            'cat-1',
            5
        );
        
        if ($book->getTotalCopies()->getValue() !== 5) {
            throw new \Exception('Book should have 5 total copies');
        }
        if ($book->getAvailableCopies()->getValue() !== 5) {
            throw new \Exception('Book should have 5 available copies initially');
        }
        
        // Test duplicate ISBN
        try {
            $createService->createBook('Another Book', 'Another Author', '1234567890123', 'cat-1', 3);
            throw new \Exception('Should reject duplicate ISBN');
        } catch (\Library\Application\Services\DuplicateIsbnException $e) {
            // Expected
        }
        
        // Test book editing
        $editedBook = $editService->updateBook(
            $book->getId(),
            'Updated Title',
            'Updated Author',
            '1234567890124', // New ISBN
            'cat-2',
            7 // Increase total copies
        );
        
        if ($editedBook->getTotalCopies()->getValue() !== 7) {
            throw new \Exception('Book should have 7 total copies after edit');
        }
        if ($editedBook->getAvailableCopies()->getValue() !== 7) {
            throw new \Exception('Available copies should increase with total');
        }
        
        // Test logical deletion
        $deleteService->deleteBook($book->getId());
        $deletedBook = $bookRepo->findById($book->getId());
        if ($deletedBook !== null) {
            throw new \Exception('Deleted book should not be findable');
        }
        
        $this->line('  ✓ HU-03: Book management working correctly');
    }

    private function verifySearchBooks(): void
    {
        $this->info('Testing HU-04: Search Books...');
        
        // Setup fakes
        $bookRepo = new \Tests\Unit\Application\Fakes\FakeBookRepository();
        $coverStorage = new \Tests\Unit\Application\Fakes\FakeCoverStorage();
        
        $createService = new \Library\Application\Services\CreateBookService($bookRepo, $coverStorage);
        $searchService = new \Library\Application\Services\SearchBooksService($bookRepo);
        
        // Create test books
        $book1 = $createService->createBook('PHP Programming', 'John Doe', '1111111111111', 'cat-1', 3);
        $book2 = $createService->createBook('Laravel Guide', 'Jane Smith', '2222222222222', 'cat-1', 2);
        $book3 = $createService->createBook('Python Basics', 'Bob Wilson', '3333333333333', 'cat-2', 1);
        
        // Test search without filters
        $result = $searchService->searchBooks(null, null, 1, 10);
        if ($result->total !== 3) {
            throw new \Exception('Search should return 3 books total');
        }
        
        // Test search by title
        $result = $searchService->searchBooks('PHP', null, 1, 10);
        if ($result->total !== 1) {
            throw new \Exception('PHP search should return 1 book');
        }
        
        // Test search by author
        $result = $searchService->searchBooks('Jane', null, 1, 10);
        if ($result->total !== 1) {
            throw new \Exception('Jane search should return 1 book');
        }
        
        // Test category filter
        $result = $searchService->searchBooks(null, 'cat-2', 1, 10);
        if ($result->total !== 1) {
            throw new \Exception('Category cat-2 should return 1 book');
        }
        
        // Test pagination limit
        try {
            $searchService->searchBooks(null, null, 1, 51);
            throw new \Exception('Should reject page size > 50');
        } catch (\Library\Application\Services\InvalidPaginationException $e) {
            // Expected
        }
        
        // Test find by ID
        $foundBook = $searchService->findBookById($book1->getId());
        if ($foundBook->getTitle() !== 'PHP Programming') {
            throw new \Exception('Found book should have correct title');
        }
        
        $this->line('  ✓ HU-04: Search books working correctly');
    }
}