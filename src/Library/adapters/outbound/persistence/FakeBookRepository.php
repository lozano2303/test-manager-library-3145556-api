<?php

namespace Library\Adapters\Outbound\Persistence;

use Library\Application\Ports\Outbound\BookRepositoryPort;
use Library\Domain\Entities\Book;
use Library\Domain\ValueObjects\Isbn;
use Library\Domain\ValueObjects\Quantity;

/**
 * Fake Book Repository
 * 
 * In-memory implementation for testing.
 * Maintains consistency with domain rules.
 */
final class FakeBookRepository implements BookRepositoryPort
{
    /** @var Book[] */
    private array $books = [];
    
    /** @var string[] ISBN -> Book ID mapping for active books only */
    private array $isbnIndex = [];
    
    /** @var string[] Book ID -> loan status (for testing open loans) */
    private array $openLoans = [];
    
    private int $nextIdCounter = 1;

    public function save(Book $book): void
    {
        $this->books[$book->id()] = $book;
        
        // Update ISBN index (only for active books)
        if ($book->isActive()) {
            $this->isbnIndex[$book->isbn()->value()] = $book->id();
        } else {
            // Remove from index if deleted
            unset($this->isbnIndex[$book->isbn()->value()]);
        }
    }

    public function findById(string $id): ?Book
    {
        return $this->books[$id] ?? null;
    }

    public function findByIsbn(Isbn $isbn): ?Book
    {
        $bookId = $this->isbnIndex[$isbn->value()] ?? null;
        return $bookId ? $this->books[$bookId] : null;
    }

    public function existsByIsbn(Isbn $isbn): bool
    {
        return isset($this->isbnIndex[$isbn->value()]);
    }

    public function search(array $filters, int $page, int $size): array
    {
        $activeBooks = array_filter($this->books, fn(Book $book) => $book->isActive());
        
        // Apply text filter (title/author search)
        if (isset($filters['text'])) {
            $searchText = mb_strtolower($filters['text'], 'UTF-8');
            $activeBooks = array_filter($activeBooks, function(Book $book) use ($searchText) {
                $title = mb_strtolower($book->title(), 'UTF-8');
                $author = mb_strtolower($book->author(), 'UTF-8');
                return str_contains($title, $searchText) || str_contains($author, $searchText);
            });
        }
        
        // Apply category filter
        if (isset($filters['categoryId'])) {
            $categoryId = $filters['categoryId'];
            $activeBooks = array_filter($activeBooks, fn(Book $book) => $book->categoryId() === $categoryId);
        }
        
        // Sort by title (CA-04.1)
        usort($activeBooks, fn(Book $a, Book $b) => strcmp($a->title(), $b->title()));
        
        // Calculate pagination
        $total = count($activeBooks);
        $offset = ($page - 1) * $size;
        $paginatedBooks = array_slice($activeBooks, $offset, $size);
        
        return [
            'books' => $paginatedBooks,
            'total' => $total
        ];
    }

    public function hasOpenLoans(string $bookId): bool
    {
        return isset($this->openLoans[$bookId]);
    }

    public function nextId(): string
    {
        return 'book-' . $this->nextIdCounter++;
    }

    /**
     * Test helper: clear all data
     */
    public function clear(): void
    {
        $this->books = [];
        $this->isbnIndex = [];
        $this->openLoans = [];
        $this->nextIdCounter = 1;
    }

    /**
     * Test helper: get all books
     */
    public function getAll(): array
    {
        return array_values($this->books);
    }

    /**
     * Test helper: set open loan status for testing
     */
    public function setOpenLoanStatus(string $bookId, bool $hasOpenLoans): void
    {
        if ($hasOpenLoans) {
            $this->openLoans[$bookId] = true;
        } else {
            unset($this->openLoans[$bookId]);
        }
    }

    /**
     * Test helper: seed with test data
     */
    public function seed(): void
    {
        // Create some test books
        $book1 = Book::create(
            $this->nextId(),
            'The Clean Code',
            'Robert C. Martin',
            Isbn::from('9780132350884'),
            'tech',
            Quantity::from(3)
        );
        $this->save($book1);

        $book2 = Book::create(
            $this->nextId(),
            'Design Patterns',
            'Gang of Four',
            Isbn::from('9780201633610'),
            'tech',
            Quantity::from(2)
        );
        $this->save($book2);

        $book3 = Book::create(
            $this->nextId(),
            '1984',
            'George Orwell',
            Isbn::from('9780451524935'),
            'fiction',
            Quantity::from(5)
        );
        $this->save($book3);
    }
}