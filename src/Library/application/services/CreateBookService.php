<?php

namespace Library\Application\Services;

use Library\Application\Ports\Outbound\BookRepositoryPort;
use Library\Application\Ports\Outbound\CoverStoragePort;
use Library\Domain\Entities\Book;
use Library\Domain\ValueObjects\Isbn;
use Library\Domain\ValueObjects\Quantity;

/**
 * Create Book Service
 * 
 * Implements HU-03: Admin manages books (creation)
 */
class CreateBookService
{
    public function __construct(
        private BookRepositoryPort $bookRepository,
        private CoverStoragePort $coverStorage
    ) {}

    /**
     * Create a new book
     * 
     * @throws DuplicateIsbnException if ISBN already exists (CA-03.2)
     * @throws InvalidQuantityException if total copies < 1 (CA-03.3)
     */
    public function createBook(
        string $title,
        string $author,
        string $isbn,
        string $categoryId,
        int $totalCopies,
        $coverFile = null,
        ?string $coverMimeType = null,
        ?int $coverSize = null
    ): Book {
        // CA-03.3: Total copies >= 1
        if ($totalCopies < 1) {
            throw new InvalidQuantityException('Total copies must be at least 1');
        }

        $isbnVO = new Isbn($isbn);
        
        // CA-03.2: ISBN unique among active books
        if ($this->bookRepository->existsByIsbn($isbnVO->getValue())) {
            throw new DuplicateIsbnException('ISBN already exists');
        }
        
        $totalQuantity = new Quantity($totalCopies);
        
        // CA-03.4: Handle cover upload (optional)
        $coverKey = null;
        if ($coverFile !== null) {
            $coverKey = $this->coverStorage->store($coverFile, $coverMimeType, $coverSize);
        }
        
        // CA-03.1: Available copies start equal to total
        $bookId = $this->bookRepository->nextId();
        $book = Book::create(
            $bookId,
            $title,
            $author,
            $isbnVO,
            $categoryId,
            $totalQuantity,
            $totalQuantity, // Available = total initially
            $coverKey
        );
        
        $this->bookRepository->save($book);
        
        return $book;
    }
}