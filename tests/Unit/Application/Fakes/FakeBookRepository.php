<?php

namespace Tests\Unit\Application\Fakes;

use Library\Application\Ports\Outbound\BookRepositoryPort;
use Library\Application\DTOs\BookSearchResult;
use Library\Domain\Entities\Book;

/**
 * Fake Book Repository for Testing
 */
class FakeBookRepository implements BookRepositoryPort
{
    private array $books = [];
    private array $openLoans = [];
    private int $nextId = 1;

    public function save(Book $book): void
    {
        $this->books[$book->getId()] = $book;
    }

    public function findById(string $id): ?Book
    {
        $book = $this->books[$id] ?? null;
        return ($book && !$book->isDeleted()) ? $book : null;
    }

    public function findByIsbn(string $isbn): ?Book
    {
        foreach ($this->books as $book) {
            if ($book->getIsbn()->getValue() === $isbn && !$book->isDeleted()) {
                return $book;
            }
        }
        return null;
    }

    public function existsByIsbn(string $isbn): bool
    {
        return $this->findByIsbn($isbn) !== null;
    }

    public function hasOpenLoans(string $bookId): bool
    {
        return in_array($bookId, $this->openLoans);
    }

    public function search(
        ?string $query = null,
        ?string $categoryId = null,
        int $page = 1,
        int $size = 20
    ): BookSearchResult {
        $filteredBooks = [];
        
        foreach ($this->books as $book) {
            if ($book->isDeleted()) continue;
            
            $matches = true;
            
            if ($query !== null) {
                $searchText = strtolower($query);
                $titleMatch = strpos(strtolower($book->getTitle()), $searchText) !== false;
                $authorMatch = strpos(strtolower($book->getAuthor()), $searchText) !== false;
                $matches = $titleMatch || $authorMatch;
            }
            
            if ($matches && $categoryId !== null) {
                $matches = $book->getCategoryId() === $categoryId;
            }
            
            if ($matches) {
                $filteredBooks[] = $book;
            }
        }
        
        // Sort by title
        usort($filteredBooks, fn($a, $b) => strcasecmp($a->getTitle(), $b->getTitle()));
        
        $total = count($filteredBooks);
        $offset = ($page - 1) * $size;
        $pagedBooks = array_slice($filteredBooks, $offset, $size);
        
        return new BookSearchResult($pagedBooks, $total, $page, $size);
    }

    public function nextId(): string
    {
        return (string) $this->nextId++;
    }

    // Test helper methods
    public function reset(): void
    {
        $this->books = [];
        $this->openLoans = [];
        $this->nextId = 1;
    }

    public function addBook(Book $book): void
    {
        $this->books[$book->getId()] = $book;
    }

    public function setHasOpenLoans(string $bookId, bool $hasLoans): void
    {
        if ($hasLoans) {
            $this->openLoans[] = $bookId;
        } else {
            $this->openLoans = array_filter($this->openLoans, fn($id) => $id !== $bookId);
        }
    }

    public function getBookCount(): int
    {
        return count(array_filter($this->books, fn($book) => !$book->isDeleted()));
    }
}