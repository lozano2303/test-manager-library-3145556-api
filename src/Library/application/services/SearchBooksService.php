<?php

namespace Library\Application\Services;

use Library\Application\Ports\Outbound\BookRepositoryPort;
use Library\Application\DTOs\BookSearchResult;
use Library\Domain\Entities\Book;

/**
 * Search Books Service
 * 
 * Implements HU-04: Search books functionality
 */
class SearchBooksService
{
    public function __construct(
        private BookRepositoryPort $bookRepository
    ) {}

    /**
     * Search books with pagination
     * 
     * @throws InvalidPaginationException when pagination parameters are invalid
     */
    public function searchBooks(
        ?string $query = null,
        ?string $categoryId = null,
        int $page = 1,
        int $size = 20
    ): BookSearchResult {
        // CA-04.1: Maximum page size is 50
        if ($size > 50) {
            throw new InvalidPaginationException('Page size cannot exceed 50');
        }
        
        if ($page < 1 || $size < 1) {
            throw new InvalidPaginationException('Page and size must be positive integers');
        }
        
        // CA-04.1: Search by partial text in title/author, filter by category, active books only
        return $this->bookRepository->search($query, $categoryId, $page, $size);
    }

    /**
     * Find book by ID
     * 
     * @throws BookNotFoundException when book not found or deleted (CA-04.3)
     */
    public function findBookById(string $id): Book
    {
        $book = $this->bookRepository->findById($id);
        
        // CA-04.3: Return 404 for non-existent or deleted books
        if (!$book || $book->isDeleted()) {
            throw new BookNotFoundException('Book not found');
        }
        
        return $book;
    }
}