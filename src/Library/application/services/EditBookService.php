<?php

namespace Library\Application\Services;

use Library\Application\Ports\Outbound\BookRepositoryPort;
use Library\Application\Ports\Outbound\CoverStoragePort;
use Library\Domain\Entities\Book;
use Library\Domain\ValueObjects\Isbn;
use Library\Domain\ValueObjects\Quantity;

/**
 * Edit Book Service
 * 
 * Implements HU-03: Admin manages books (editing)
 */
class EditBookService
{
    public function __construct(
        private BookRepositoryPort $bookRepository,
        private CoverStoragePort $coverStorage
    ) {}

    /**
     * Update book details
     * 
     * @throws BookNotFoundException if book not found
     * @throws DuplicateIsbnException if new ISBN already exists
     * @throws InvalidQuantityException if business rules violated
     */
    public function updateBook(
        string $bookId,
        string $title,
        string $author,
        string $isbn,
        string $categoryId,
        int $totalCopies,
        $coverFile = null,
        ?string $coverMimeType = null,
        ?int $coverSize = null
    ): Book {
        $book = $this->bookRepository->findById($bookId);
        
        if (!$book || $book->isDeleted()) {
            throw new BookNotFoundException('Book not found');
        }

        // CA-03.3: Total copies >= 1
        if ($totalCopies < 1) {
            throw new InvalidQuantityException('Total copies must be at least 1');
        }

        $newIsbn = new Isbn($isbn);
        
        // Check ISBN uniqueness only if changed
        if ($newIsbn->getValue() !== $book->getIsbn()->getValue()) {
            if ($this->bookRepository->existsByIsbn($newIsbn->getValue())) {
                throw new DuplicateIsbnException('ISBN already exists');
            }
        }

        $newTotal = new Quantity($totalCopies);
        $currentAvailable = $book->getAvailableCopies()->getValue();
        $currentTotal = $book->getTotalCopies()->getValue();
        
        // CA-03.3: Adjust available copies, never go below 0
        $difference = $newTotal->getValue() - $currentTotal;
        $newAvailable = $currentAvailable + $difference;
        
        if ($newAvailable < 0) {
            throw new InvalidQuantityException('Cannot reduce total below current loans');
        }

        // Handle cover update
        $coverKey = $book->getCoverKey();
        if ($coverFile !== null) {
            // Delete old cover
            if ($coverKey !== null) {
                $this->coverStorage->delete($coverKey);
            }
            $coverKey = $this->coverStorage->store($coverFile, $coverMimeType, $coverSize);
        }

        // Create updated book
        $updatedBook = Book::create(
            $bookId,
            $title,
            $author,
            $newIsbn,
            $categoryId,
            $newTotal,
            new Quantity($newAvailable),
            $coverKey
        );

        $this->bookRepository->save($updatedBook);
        
        return $updatedBook;
    }
}