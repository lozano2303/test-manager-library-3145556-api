<?php

namespace Library\Application\Ports\Outbound;

use Library\Domain\Entities\Book;
use Library\Application\DTOs\BookSearchResult;

/**
 * Book Repository Port
 * 
 * Defines the contract for book persistence operations.
 */
interface BookRepositoryPort
{
    /**
     * Save a book (create or update)
     */
    public function save(Book $book): void;

    /**
     * Find book by ID (only active books)
     */
    public function findById(string $id): ?Book;

    /**
     * Find book by ISBN (only active books)
     */
    public function findByIsbn(string $isbn): ?Book;

    /**
     * Check if ISBN exists among active books
     */
    public function existsByIsbn(string $isbn): bool;

    /**
     * Check if book has open loans
     */
    public function hasOpenLoans(string $bookId): bool;

    /**
     * Search books with filters and pagination
     */
    public function search(
        ?string $query = null,
        ?string $categoryId = null,
        int $page = 1,
        int $size = 20
    ): BookSearchResult;

    /**
     * Generate next book ID
     */
    public function nextId(): string;
}