<?php

namespace Library\Application\Services;

use Library\Application\Ports\Outbound\BookRepositoryPort;
use Library\Domain\Entities\Book;

/**
 * Delete Book Service
 * 
 * Implements HU-03: Admin manages books (logical deletion)
 */
class DeleteBookService
{
    public function __construct(
        private BookRepositoryPort $bookRepository
    ) {}

    /**
     * Logically delete a book
     * 
     * @throws BookNotFoundException if book not found
     * @throws BookHasLoansException if book has open loans (CA-03.5)
     */
    public function deleteBook(string $bookId): void
    {
        $book = $this->bookRepository->findById($bookId);
        
        if (!$book || $book->isDeleted()) {
            throw new BookNotFoundException('Book not found');
        }

        // CA-03.5: Cannot delete book with open loans
        if ($this->bookRepository->hasOpenLoans($bookId)) {
            throw new BookHasLoansException('Cannot delete book with open loans');
        }

        // CA-03.5: Logical deletion - book stops appearing in catalog
        $deletedBook = Book::create(
            $book->getId(),
            $book->getTitle(),
            $book->getAuthor(),
            $book->getIsbn(),
            $book->getCategoryId(),
            $book->getTotalCopies(),
            $book->getAvailableCopies(),
            $book->getCoverKey(),
            true // Mark as deleted
        );

        $this->bookRepository->save($deletedBook);
    }
}