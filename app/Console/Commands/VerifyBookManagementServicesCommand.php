<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Library\Application\Services\CreateBookService;
use Library\Application\Services\SearchBooksService;
use Library\Application\Services\UpdateBookService;
use Library\Application\Services\BookCreationException;
use Library\Application\Services\BookSearchException;
use Library\Application\Services\BookNotFoundException;
use Library\Application\Services\BookUpdateException;
use Library\Adapters\Outbound\Persistence\FakeBookRepository;
use Library\Adapters\Outbound\Storage\FakeCoverStorage;

/**
 * Verification Command for T-08: Book Management Services
 * 
 * Demonstrates that HU-03 and HU-04 pass against fake implementations.
 */
class VerifyBookManagementServicesCommand extends Command
{
    protected $signature = 'verify:book-services';
    protected $description = 'Verify T-08 Book Management Services against fake implementations';

    public function handle(): int
    {
        $this->info('📚 Verifying T-08: Book Management Services');
        $this->newLine();

        // Setup fake implementations
        $bookRepository = new FakeBookRepository();
        $coverStorage = new FakeCoverStorage();
        
        $createBookService = new CreateBookService($bookRepository, $coverStorage);
        $searchBooksService = new SearchBooksService($bookRepository);
        $updateBookService = new UpdateBookService($bookRepository, $coverStorage);
        
        // Seed test data
        $bookRepository->seed();
        
        $this->verifyHU03($createBookService, $updateBookService, $bookRepository, $coverStorage);
        $this->verifyHU04($searchBooksService, $bookRepository);
        
        $this->newLine();
        $this->info('✅ All book management service tests passed!');
        return Command::SUCCESS;
    }

    /**
     * Verify HU-03: Book management
     */
    private function verifyHU03(
        CreateBookService $createBookService,
        UpdateBookService $updateBookService,
        FakeBookRepository $bookRepository,
        FakeCoverStorage $coverStorage
    ): void {
        $this->info('🧪 Testing HU-03: Book management');
        
        // CA-03.1: Create book with required data
        $this->comment('   Testing CA-03.1: Create book with required data...');
        try {
            $result = $createBookService->createBook(
                'Test Book',
                'Test Author',
                '9781234567890',
                'tech',
                5
            );
            
            $this->checkAssert(
                $result['title'] === 'Test Book' && 
                $result['isbn'] === '9781234567890' &&
                $result['totalCopies'] === 5 &&
                $result['availableCopies'] === 5, // CA-03.3: available = total initially
                'Book created with correct data and available = total copies'
            );
            
        } catch (\Exception $e) {
            $this->fail("CA-03.1 failed: " . $e->getMessage());
        }
        
        // CA-03.2: ISBN uniqueness
        $this->comment('   Testing CA-03.2: ISBN uniqueness (409 for duplicate)...');
        try {
            $createBookService->createBook(
                'Duplicate ISBN Book',
                'Author',
                '9780132350884', // Existing ISBN from seed
                'tech',
                3
            );
            $this->fail('Should have thrown BookCreationException for duplicate ISBN');
        } catch (BookCreationException $e) {
            $this->checkAssert(
                $e->getStatusCode() === 409,
                'Duplicate ISBN returns 409 status'
            );
        }
        
        // CA-03.3: Total copies ≥ 1
        $this->comment('   Testing CA-03.3: Total copies validation...');
        try {
            $createBookService->createBook(
                'Invalid Book',
                'Author',
                '9781111111111',
                'tech',
                0 // Invalid
            );
            $this->fail('Should have thrown exception for total copies < 1');
        } catch (BookCreationException $e) {
            $this->checkAssert(
                str_contains($e->getMessage(), 'Total copies must be at least 1'),
                'Total copies < 1 rejected'
            );
        }
        
        // Test copy adjustment (CA-03.3: never allows available < 0)
        $bookId = null;
        try {
            $result = $createBookService->createBook(
                'Copy Test Book',
                'Author',
                '9782222222222',
                'tech',
                3
            );
            $bookId = $result['id'];
            
            // Try to reduce total copies below available (should fail)
            $book = $bookRepository->findById($bookId);
            // Simulate that 2 copies are loaned (available = 1)
            $bookWithdrawn = $book->withdraw(\Library\Domain\ValueObjects\Quantity::from(2));
            $bookRepository->save($bookWithdrawn);
            
            // Now try to set total to 1 (would make available = -1, should fail)
            $updateBookService->updateBookCopies($bookId, 1);
            $this->fail('Should have thrown exception when total would make available < 0');
        } catch (BookUpdateException $e) {
            $this->checkAssert(
                $e->getStatusCode() === 422,
                'Reducing total copies below available is rejected (CA-03.3)'
            );
        }
        
        // CA-03.4: Cover upload (JPG/PNG, max 2MB)
        $this->comment('   Testing CA-03.4: Cover upload validation...');
        
        // Valid PNG cover
        try {
            $coverFile = [
                'content' => FakeCoverStorage::createFakeImageContent('png'),
                'mimeType' => 'image/png',
                'filename' => 'cover.png'
            ];
            
            $result = $createBookService->createBook(
                'Book with Cover',
                'Author',
                '9783333333333',
                'fiction',
                2,
                $coverFile
            );
            
            $this->checkAssert(
                $result['coverKey'] !== null && str_starts_with($result['coverKey'], 'cover-'),
                'PNG cover uploaded successfully with opaque key'
            );
            
        } catch (\Exception $e) {
            $this->fail("CA-03.4 valid cover failed: " . $e->getMessage());
        }
        
        // Invalid file type
        try {
            $coverFile = [
                'content' => 'text content',
                'mimeType' => 'text/plain',
                'filename' => 'document.txt'
            ];
            
            $createBookService->createBook(
                'Invalid Cover Book',
                'Author',
                '9784444444444',
                'tech',
                1,
                $coverFile
            );
            $this->fail('Should have rejected non-image file');
        } catch (BookCreationException $e) {
            $this->checkAssert(
                $e->getStatusCode() === 422 && str_contains($e->getMessage(), 'JPG or PNG'),
                'Non-image file rejected with 422'
            );
        }
        
        // File too large (>2MB)
        try {
            $largeContent = str_repeat('x', 3 * 1024 * 1024); // 3MB
            $coverFile = [
                'content' => $largeContent,
                'mimeType' => 'image/png',
                'filename' => 'large.png'
            ];
            
            $createBookService->createBook(
                'Large Cover Book',
                'Author',
                '9785555555555',
                'tech',
                1,
                $coverFile
            );
            $this->fail('Should have rejected file > 2MB');
        } catch (BookCreationException $e) {
            $this->checkAssert(
                $e->getStatusCode() === 422 && str_contains($e->getMessage(), '2MB'),
                'File > 2MB rejected with 422'
            );
        }
        
        // CA-03.5: Logical deletion
        $this->comment('   Testing CA-03.5: Logical deletion...');
        try {
            $result = $createBookService->createBook(
                'Deletion Test Book',
                'Author',
                '9786666666666',
                'tech',
                1
            );
            $bookId = $result['id'];
            
            // Delete book (no open loans)
            $updateBookService->deleteBook($bookId);
            
            // Verify book is marked as deleted but still exists
            $book = $bookRepository->findById($bookId);
            $this->checkAssert(
                $book !== null && $book->isDeleted(),
                'Book logically deleted (still exists but marked deleted)'
            );
            
        } catch (\Exception $e) {
            $this->fail("CA-03.5 deletion failed: " . $e->getMessage());
        }
        
        // Test deletion with open loans (should fail)
        try {
            $result = $createBookService->createBook(
                'Loaned Book',
                'Author',
                '9787777777777',
                'tech',
                1
            );
            $bookId = $result['id'];
            
            // Simulate open loans
            $bookRepository->setOpenLoanStatus($bookId, true);
            
            $updateBookService->deleteBook($bookId);
            $this->fail('Should have rejected deletion of book with open loans');
        } catch (BookUpdateException $e) {
            $this->checkAssert(
                $e->getStatusCode() === 409 && str_contains($e->getMessage(), 'open loans'),
                'Book with open loans cannot be deleted (409)'
            );
        }
        
        $this->info('   ✓ HU-03 tests completed successfully');
    }

    /**
     * Verify HU-04: Book search
     */
    private function verifyHU04(SearchBooksService $searchBooksService, FakeBookRepository $bookRepository): void
    {
        $this->info('🧪 Testing HU-04: Book search');
        
        // CA-04.1: Search functionality
        $this->comment('   Testing CA-04.1: Search by text, category, pagination...');
        
        // Basic search (all active books)
        try {
            $result = $searchBooksService->searchBooks();
            
            $this->checkAssert(
                is_array($result) && isset($result['books']) && isset($result['total']),
                'Search returns books array and total count'
            );
            
            $this->checkAssert(
                $result['page'] === 1 && $result['size'] === 20,
                'Default pagination (page 1, size 20)'
            );
            
            // All books should be active
            foreach ($result['books'] as $book) {
                if (!$book['isActive']) {
                    $this->fail('Inactive book found in search results');
                }
            }
            $this->checkAssert(true, 'Only active books in search results');
            
        } catch (\Exception $e) {
            $this->fail("CA-04.1 basic search failed: " . $e->getMessage());
        }
        
        // Text search (title/author)
        try {
            $result = $searchBooksService->searchBooks('Clean');
            $this->checkAssert(
                $result['total'] > 0,
                'Text search finds books by title'
            );
            
            $result = $searchBooksService->searchBooks('Martin');
            $this->checkAssert(
                $result['total'] > 0,
                'Text search finds books by author'
            );
            
        } catch (\Exception $e) {
            $this->fail("CA-04.1 text search failed: " . $e->getMessage());
        }
        
        // Category filter
        try {
            $result = $searchBooksService->searchBooks(null, 'tech');
            foreach ($result['books'] as $book) {
                if ($book['categoryId'] !== 'tech') {
                    $this->fail('Wrong category in filtered results');
                }
            }
            $this->checkAssert(true, 'Category filter works correctly');
            
        } catch (\Exception $e) {
            $this->fail("CA-04.1 category filter failed: " . $e->getMessage());
        }
        
        // Pagination limits
        try {
            $searchBooksService->searchBooks(null, null, 1, 51); // > 50
            $this->fail('Should have rejected page size > 50');
        } catch (BookSearchException $e) {
            $this->checkAssert(
                str_contains($e->getMessage(), 'cannot exceed 50'),
                'Page size limited to 50 (CA-04.1)'
            );
        }
        
        // CA-04.2: Response includes total count
        $this->comment('   Testing CA-04.2: Response includes total count...');
        try {
            $result = $searchBooksService->searchBooks();
            $this->checkAssert(
                isset($result['total']) && is_int($result['total']),
                'Response includes total count as integer'
            );
            
        } catch (\Exception $e) {
            $this->fail("CA-04.2 failed: " . $e->getMessage());
        }
        
        // CA-04.3: Get book by ID
        $this->comment('   Testing CA-04.3: Get book by ID (404 for nonexistent/deleted)...');
        
        // Get existing book
        try {
            $searchResult = $searchBooksService->searchBooks();
            $bookId = $searchResult['books'][0]['id'];
            
            $result = $searchBooksService->getBookById($bookId);
            $this->checkAssert(
                $result['id'] === $bookId,
                'Get existing book by ID works'
            );
            
        } catch (\Exception $e) {
            $this->fail("CA-04.3 get existing book failed: " . $e->getMessage());
        }
        
        // Nonexistent book ID
        try {
            $searchBooksService->getBookById('nonexistent-id');
            $this->fail('Should have thrown BookNotFoundException for nonexistent ID');
        } catch (BookNotFoundException $e) {
            $this->checkAssert(
                str_contains($e->getMessage(), 'not found'),
                'Nonexistent book ID throws 404 (BookNotFoundException)'
            );
        }
        
        // Deleted book ID
        try {
            // Create and delete a book
            $book = \Library\Domain\Entities\Book::create(
                'deleted-test-id',
                'Deleted Book',
                'Author',
                \Library\Domain\ValueObjects\Isbn::from('9999999999999'),
                'tech',
                \Library\Domain\ValueObjects\Quantity::from(1)
            );
            $deletedBook = $book->delete();
            $bookRepository->save($deletedBook);
            
            $searchBooksService->getBookById('deleted-test-id');
            $this->fail('Should have thrown BookNotFoundException for deleted book');
        } catch (BookNotFoundException $e) {
            $this->checkAssert(
                str_contains($e->getMessage(), 'not found'),
                'Deleted book ID throws 404 (BookNotFoundException)'
            );
        }
        
        $this->info('   ✓ HU-04 tests completed successfully');
    }

    private function checkAssert(bool $condition, string $message): void
    {
        if ($condition) {
            $this->line("     ✓ {$message}");
        } else {
            $this->error("     ✗ {$message}");
            throw new \Exception("Assertion failed: {$message}");
        }
    }
}