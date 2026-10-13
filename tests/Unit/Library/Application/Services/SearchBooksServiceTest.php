<?php

namespace Tests\Unit\Library\Application\Services;

use PHPUnit\Framework\TestCase;
use Library\Application\Services\SearchBooksService;
use Library\Application\Services\BookSearchException;
use Library\Application\Services\BookNotFoundException;
use Library\Adapters\Outbound\Persistence\FakeBookRepository;
use Library\Domain\Entities\Book;
use Library\Domain\ValueObjects\Isbn;
use Library\Domain\ValueObjects\Quantity;

/**
 * SearchBooksService Test
 * 
 * Tests HU-04: Book search
 * - CA-04.1: Search by text (title/author), filter by category, active only, paginated
 * - CA-04.2: Response includes total count
 * - CA-04.3: Nonexistent/deleted book by ID → 404
 */
final class SearchBooksServiceTest extends TestCase
{
    private SearchBooksService $searchBooksService;
    private FakeBookRepository $bookRepository;

    protected function setUp(): void
    {
        $this->bookRepository = new FakeBookRepository();
        
        $this->searchBooksService = new SearchBooksService(
            $this->bookRepository
        );
        
        // Seed test data
        $this->bookRepository->seed();
        
        // Add more diverse test books
        $this->addMoreTestBooks();
    }

    /**
     * @test
     * CA-04.1: Search without filters returns all active books
     */
    public function search_without_filters_returns_all_active_books(): void
    {
        // Act
        $result = $this->searchBooksService->searchBooks();
        
        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('books', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('page', $result);
        $this->assertArrayHasKey('size', $result);
        
        $this->assertEquals(1, $result['page']);
        $this->assertEquals(20, $result['size']); // Default size
        $this->assertGreaterThan(0, $result['total']);
        $this->assertIsArray($result['books']);
        
        // All returned books should be active
        foreach ($result['books'] as $book) {
            $this->assertTrue($book['isActive']);
        }
    }

    /**
     * @test
     * CA-04.1: Text search in title works correctly
     */
    public function text_search_in_title_works_correctly(): void
    {
        // Act - search for "Clean" (matches "The Clean Code")
        $result = $this->searchBooksService->searchBooks('Clean');
        
        // Assert
        $this->assertGreaterThan(0, $result['total']);
        $this->assertContains('Clean', $result['books'][0]['title']);
    }

    /**
     * @test
     * CA-04.1: Text search in author works correctly
     */
    public function text_search_in_author_works_correctly(): void
    {
        // Act - search for "Martin" (matches "Robert C. Martin")
        $result = $this->searchBooksService->searchBooks('Martin');
        
        // Assert
        $this->assertGreaterThan(0, $result['total']);
        
        $found = false;
        foreach ($result['books'] as $book) {
            if (str_contains(strtolower($book['author']), 'martin')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Should find books by author Martin');
    }

    /**
     * @test
     * CA-04.1: Case-insensitive text search
     */
    public function case_insensitive_text_search(): void
    {
        // Act - search with different cases
        $result1 = $this->searchBooksService->searchBooks('clean');
        $result2 = $this->searchBooksService->searchBooks('CLEAN');
        $result3 = $this->searchBooksService->searchBooks('Clean');
        
        // Assert - all should return same results
        $this->assertEquals($result1['total'], $result2['total']);
        $this->assertEquals($result1['total'], $result3['total']);
    }

    /**
     * @test
     * CA-04.1: Category filter works correctly
     */
    public function category_filter_works_correctly(): void
    {
        // Act - filter by 'tech' category
        $result = $this->searchBooksService->searchBooks(null, 'tech');
        
        // Assert
        $this->assertGreaterThan(0, $result['total']);
        
        // All returned books should be in 'tech' category
        foreach ($result['books'] as $book) {
            $this->assertEquals('tech', $book['categoryId']);
        }
    }

    /**
     * @test
     * CA-04.1: Combined text and category filter
     */
    public function combined_text_and_category_filter(): void
    {
        // Act - search for "Code" in 'tech' category
        $result = $this->searchBooksService->searchBooks('Code', 'tech');
        
        // Assert
        $this->assertGreaterThanOrEqual(0, $result['total']);
        
        foreach ($result['books'] as $book) {
            $this->assertEquals('tech', $book['categoryId']);
            $this->assertTrue(
                str_contains(strtolower($book['title']), 'code') ||
                str_contains(strtolower($book['author']), 'code')
            );
        }
    }

    /**
     * @test
     * CA-04.1: Pagination works correctly
     */
    public function pagination_works_correctly(): void
    {
        // Act - get first page with size 2
        $page1 = $this->searchBooksService->searchBooks(null, null, 1, 2);
        $page2 = $this->searchBooksService->searchBooks(null, null, 2, 2);
        
        // Assert
        $this->assertEquals(1, $page1['page']);
        $this->assertEquals(2, $page1['size']);
        $this->assertEquals(2, $page2['page']);
        $this->assertEquals(2, $page2['size']);
        
        // Same total for both pages
        $this->assertEquals($page1['total'], $page2['total']);
        
        // Different books (assuming we have enough test data)
        if ($page1['total'] > 2) {
            $this->assertNotEquals($page1['books'][0]['id'], $page2['books'][0]['id']);
        }
    }

    /**
     * @test
     * CA-04.1: Max page size is 50
     */
    public function max_page_size_is_50(): void
    {
        $this->expectException(BookSearchException::class);
        $this->expectExceptionMessage('Page size cannot exceed 50');
        
        $this->searchBooksService->searchBooks(null, null, 1, 51);
    }

    /**
     * @test
     * Invalid page number throws exception
     */
    public function invalid_page_number_throws_exception(): void
    {
        $this->expectException(BookSearchException::class);
        $this->expectExceptionMessage('Page must be at least 1');
        
        $this->searchBooksService->searchBooks(null, null, 0, 10);
    }

    /**
     * @test
     * Invalid page size throws exception
     */
    public function invalid_page_size_throws_exception(): void
    {
        $this->expectException(BookSearchException::class);
        $this->expectExceptionMessage('Page size must be at least 1');
        
        $this->searchBooksService->searchBooks(null, null, 1, 0);
    }

    /**
     * @test
     * CA-04.2: Response includes total count
     */
    public function response_includes_total_count(): void
    {
        // Act
        $result = $this->searchBooksService->searchBooks();
        
        // Assert - CA-04.2
        $this->assertArrayHasKey('total', $result);
        $this->assertIsInt($result['total']);
        $this->assertGreaterThanOrEqual(0, $result['total']);
        
        // Total should match actual count of books returned across all pages
        $actualTotal = count($this->bookRepository->getAll());
        // Subtract deleted books
        $activeBooks = array_filter(
            $this->bookRepository->getAll(), 
            fn($book) => $book->isActive()
        );
        $this->assertEquals(count($activeBooks), $result['total']);
    }

    /**
     * @test
     * CA-04.3: Get existing book by ID works
     */
    public function get_existing_book_by_id_works(): void
    {
        // Arrange - get a book from search first
        $searchResult = $this->searchBooksService->searchBooks();
        $bookId = $searchResult['books'][0]['id'];
        
        // Act
        $result = $this->searchBooksService->getBookById($bookId);
        
        // Assert
        $this->assertIsArray($result);
        $this->assertEquals($bookId, $result['id']);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('author', $result);
        $this->assertArrayHasKey('isbn', $result);
    }

    /**
     * @test
     * CA-04.3: Nonexistent book by ID throws 404
     */
    public function nonexistent_book_by_id_throws_404(): void
    {
        $this->expectException(BookNotFoundException::class);
        $this->expectExceptionMessage('Book not found');
        
        $this->searchBooksService->getBookById('nonexistent-id');
    }

    /**
     * @test
     * CA-04.3: Deleted book by ID throws 404
     */
    public function deleted_book_by_id_throws_404(): void
    {
        // Arrange - create and delete a book
        $book = Book::create(
            'deleted-book',
            'Deleted Book',
            'Author',
            Isbn::from('9999999999999'),
            'tech',
            Quantity::from(1)
        );
        $deletedBook = $book->delete();
        $this->bookRepository->save($deletedBook);
        
        // Act & Assert
        $this->expectException(BookNotFoundException::class);
        $this->expectExceptionMessage('Book not found');
        
        $this->searchBooksService->getBookById('deleted-book');
    }

    /**
     * @test
     * CA-04.1: Only active books appear in search results
     */
    public function only_active_books_appear_in_search(): void
    {
        // Arrange - create and delete a book
        $book = Book::create(
            'deleted-book-2',
            'Deleted Book 2',
            'Author',
            Isbn::from('9999999999998'),
            'tech',
            Quantity::from(1)
        );
        $this->bookRepository->save($book);
        
        // Search should include it
        $result1 = $this->searchBooksService->searchBooks('Deleted Book 2');
        $this->assertEquals(1, $result1['total']);
        
        // Delete the book
        $deletedBook = $book->delete();
        $this->bookRepository->save($deletedBook);
        
        // Search should not include it anymore
        $result2 = $this->searchBooksService->searchBooks('Deleted Book 2');
        $this->assertEquals(0, $result2['total']);
    }

    /**
     * @test
     * Books are ordered by title (CA-04.1)
     */
    public function books_are_ordered_by_title(): void
    {
        // Act
        $result = $this->searchBooksService->searchBooks();
        
        // Assert - books should be ordered alphabetically by title
        $titles = array_column($result['books'], 'title');
        $sortedTitles = $titles;
        sort($sortedTitles);
        
        $this->assertEquals($sortedTitles, $titles);
    }

    /**
     * Add more test books for comprehensive testing
     */
    private function addMoreTestBooks(): void
    {
        $books = [
            ['PHP Mastery', 'Jane Smith', '9781111111111', 'tech'],
            ['Laravel Guide', 'Bob Johnson', '9782222222222', 'tech'],
            ['Fiction Novel', 'Alice Writer', '9783333333333', 'fiction'],
            ['Science Book', 'Dr. Science', '9784444444444', 'science'],
            ['Another PHP Book', 'Code Master', '9785555555555', 'tech'],
        ];
        
        foreach ($books as [$title, $author, $isbn, $category]) {
            $book = Book::create(
                $this->bookRepository->nextId(),
                $title,
                $author,
                Isbn::from($isbn),
                $category,
                Quantity::from(3)
            );
            $this->bookRepository->save($book);
        }
    }
}