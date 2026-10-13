<?php

namespace Library\Adapters\Outbound\Persistence;

use Library\Application\Ports\Outbound\BookRepositoryPort;
use Library\Application\DTOs\BookSearchResult;
use Library\Domain\Entities\Book;

class EloquentBookRepository implements BookRepositoryPort
{
    public function save(Book $book): void
    {
        // TODO: Eloquent implementation
    }

    public function findById(string $id): ?Book
    {
        // TODO: Eloquent implementation
        return null;
    }

    public function findByIsbn(string $isbn): ?Book
    {
        return null;
    }

    public function existsByIsbn(string $isbn): bool
    {
        return false;
    }

    public function hasOpenLoans(string $bookId): bool
    {
        return false;
    }

    public function search(?string $query = null, ?string $categoryId = null, int $page = 1, int $size = 20): BookSearchResult
    {
        return new BookSearchResult([], 0, $page, $size);
    }

    public function nextId(): string
    {
        return uniqid();
    }
}