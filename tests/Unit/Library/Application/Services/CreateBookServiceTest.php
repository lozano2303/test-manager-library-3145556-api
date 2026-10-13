<?php

namespace Tests\Unit\Library\Application\Services;

use PHPUnit\Framework\TestCase;
use Library\Application\Services\CreateBookService;
use Library\Application\Services\BookCreationException;
use Library\Adapters\Outbound\Persistence\FakeBookRepository;
use Library\Adapters\Outbound\Storage\FakeCoverStorage;

/**
 * CreateBookService Test
 * 
 * Tests HU-03: Book management (admin)
 * - CA-03.1: Create book with title, author, ISBN, category, total copies
 * - CA-03.2: ISBN 13 digits, unique among active books → 409 if duplicate
 * - CA-03.3: Total copies ≥ 1, available starts equal to total
 * - CA-03.4: Cover upload (JPG/PNG, max 2MB) → opaque key or null
 */
final class CreateBookServiceTest extends TestCase
{
    private CreateBookService $createBookService;
    private FakeBookRepository $bookRepository;
    private FakeCoverStorage $coverStorage;

    protected function setUp(): void
    {
        $this->bookRepository = new FakeBookRepository();
        $this->coverStorage = new FakeCoverStorage();
        
        $this->createBookService = new CreateBookService(
            $this->bookRepository,
            $this->coverStorage
        );
        
        // Seed test data
        $this->bookRepository->seed();
    }

    /**
     * @test
     * CA-03.1: Successfully create book with valid data
     */
    public function successfully_creates_book_with_valid_data(): void
    {
        // Act
        $result = $this->createBookService->createBook(
            'Effective PHP',
            'John Doe',
            '9781234567890',
            'tech',
            5
        );
        
        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertEquals('Effective PHP', $result['title']);
        $this->assertEquals('John Doe', $result['author']);
        $this->assertEquals('9781234567890', $result['isbn']);
        $this->assertEquals('tech', $result['categoryId']);
        $this->assertEquals(5, $result['totalCopies']);
        $this->assertEquals(5, $result['availableCopies']); // CA-03.3: available = total initially
        $this->assertNull($result['coverKey']); // No cover provided
        $this->assertTrue($result['isActive']);
        
        // Verify book was saved
        $book = $this->bookRepository->findById($result['id']);
        $this->assertNotNull($book);
    }

    /**
     * @test
     * CA-03.2: Duplicate ISBN throws 409 exception
     */
    public function duplicate_isbn_throws_409_exception(): void
    {
        $this->expectException(BookCreationException::class);
        $this->expectExceptionMessage('ISBN already exists among active books');
        
        try {
            $this->createBookService->createBook(
                'Another Book',
                'Another Author',
                '9780132350884', // Existing ISBN from seed
                'tech',
                3
            );
        } catch (BookCreationException $e) {
            $this->assertEquals(409, $e->getStatusCode());
            throw $e;
        }
    }

    /**
     * @test
     * CA-03.2: ISBN validation - must be 13 digits
     */
    public function invalid_isbn_format_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ISBN must be exactly 13 digits');
        
        $this->createBookService->createBook(
            'Test Book',
            'Test Author',
            '12345', // Invalid ISBN - too short
            'tech',
            3
        );
    }

    /**
     * @test
     * CA-03.2: ISBN must contain only digits
     */
    public function isbn_with_non_digits_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ISBN must contain only digits');
        
        $this->createBookService->createBook(
            'Test Book',
            'Test Author',
            '978-123456789', // Invalid ISBN - contains dashes
            'tech',
            3
        );
    }

    /**
     * @test
     * CA-03.3: Total copies must be at least 1
     */
    public function total_copies_must_be_at_least_one(): void
    {
        $this->expectException(BookCreationException::class);
        $this->expectExceptionMessage('Total copies must be at least 1');
        
        $this->createBookService->createBook(
            'Test Book',
            'Test Author',
            '9781234567890',
            'tech',
            0 // Invalid - must be ≥ 1
        );
    }

    /**
     * @test
     * CA-03.4: Successfully create book with cover
     */
    public function successfully_creates_book_with_cover(): void
    {
        // Arrange
        $coverFile = [
            'content' => FakeCoverStorage::createFakeImageContent('png'),
            'mimeType' => 'image/png',
            'filename' => 'cover.png'
        ];
        
        // Act
        $result = $this->createBookService->createBook(
            'Book with Cover',
            'Author Name',
            '9781234567891',
            'fiction',
            3,
            $coverFile
        );
        
        // Assert
        $this->assertNotNull($result['coverKey']);
        $this->assertStringStartsWith('cover-', $result['coverKey']);
        
        // Verify cover was stored
        $this->assertTrue($this->coverStorage->exists($result['coverKey']));
    }

    /**
     * @test
     * CA-03.4: Cover file too large throws 422 exception
     */
    public function cover_file_too_large_throws_422_exception(): void
    {
        // Arrange - create file > 2MB
        $largeContent = str_repeat('x', 3 * 1024 * 1024); // 3MB
        $coverFile = [
            'content' => $largeContent,
            'mimeType' => 'image/png',
            'filename' => 'large.png'
        ];
        
        $this->expectException(BookCreationException::class);
        $this->expectExceptionMessage('Cover file too large (max 2MB)');
        
        try {
            $this->createBookService->createBook(
                'Test Book',
                'Test Author',
                '9781234567892',
                'tech',
                3,
                $coverFile
            );
        } catch (BookCreationException $e) {
            $this->assertEquals(422, $e->getStatusCode());
            throw $e;
        }
    }

    /**
     * @test
     * CA-03.4: Invalid MIME type throws 422 exception
     */
    public function invalid_mime_type_throws_422_exception(): void
    {
        $coverFile = [
            'content' => 'some content',
            'mimeType' => 'text/plain', // Invalid - only JPG/PNG allowed
            'filename' => 'document.txt'
        ];
        
        $this->expectException(BookCreationException::class);
        $this->expectExceptionMessage('Cover must be JPG or PNG');
        
        try {
            $this->createBookService->createBook(
                'Test Book',
                'Test Author',
                '9781234567893',
                'tech',
                3,
                $coverFile
            );
        } catch (BookCreationException $e) {
            $this->assertEquals(422, $e->getStatusCode());
            throw $e;
        }
    }

    /**
     * @test
     * CA-03.4: JPEG cover works correctly
     */
    public function jpeg_cover_works_correctly(): void
    {
        $coverFile = [
            'content' => FakeCoverStorage::createFakeImageContent('jpeg'),
            'mimeType' => 'image/jpeg',
            'filename' => 'cover.jpg'
        ];
        
        $result = $this->createBookService->createBook(
            'JPEG Book',
            'Author',
            '9781234567894',
            'tech',
            2,
            $coverFile
        );
        
        $this->assertNotNull($result['coverKey']);
        $this->assertTrue($this->coverStorage->exists($result['coverKey']));
    }

    /**
     * @test
     * Empty title throws exception
     */
    public function empty_title_throws_exception(): void
    {
        $this->expectException(BookCreationException::class);
        $this->expectExceptionMessage('Title is required');
        
        $this->createBookService->createBook(
            '',
            'Author',
            '9781234567895',
            'tech',
            3
        );
    }

    /**
     * @test
     * Empty author throws exception
     */
    public function empty_author_throws_exception(): void
    {
        $this->expectException(BookCreationException::class);
        $this->expectExceptionMessage('Author is required');
        
        $this->createBookService->createBook(
            'Title',
            '',
            '9781234567896',
            'tech',
            3
        );
    }

    /**
     * @test
     * Empty category throws exception
     */
    public function empty_category_throws_exception(): void
    {
        $this->expectException(BookCreationException::class);
        $this->expectExceptionMessage('Category is required');
        
        $this->createBookService->createBook(
            'Title',
            'Author',
            '9781234567897',
            '',
            3
        );
    }

    /**
     * @test
     * Cover with empty content throws exception
     */
    public function cover_with_empty_content_throws_exception(): void
    {
        $coverFile = [
            'content' => '', // Empty
            'mimeType' => 'image/png',
            'filename' => 'empty.png'
        ];
        
        $this->expectException(BookCreationException::class);
        $this->expectExceptionMessage('Cover file content is required');
        
        $this->createBookService->createBook(
            'Test Book',
            'Author',
            '9781234567898',
            'tech',
            3,
            $coverFile
        );
    }

    /**
     * @test
     * Book ID is generated correctly
     */
    public function book_id_is_generated_correctly(): void
    {
        $result = $this->createBookService->createBook(
            'ID Test Book',
            'Author',
            '9781234567899',
            'tech',
            1
        );
        
        $this->assertNotEmpty($result['id']);
        $this->assertStringStartsWith('book-', $result['id']);
        
        // Verify book can be found by ID
        $book = $this->bookRepository->findById($result['id']);
        $this->assertNotNull($book);
        $this->assertEquals('ID Test Book', $book->title());
    }

    /**
     * @test
     * Title whitespace is trimmed
     */
    public function title_whitespace_is_trimmed(): void
    {
        $result = $this->createBookService->createBook(
            '  Trimmed Title  ',
            'Author',
            '9781234567810',
            'tech',
            1
        );
        
        $this->assertEquals('Trimmed Title', $result['title']);
    }
}