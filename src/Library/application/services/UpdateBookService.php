<?php

namespace Library\Application\Services;

use Library\Application\Ports\Outbound\BookRepositoryPort;
use Library\Application\Ports\Outbound\CoverStoragePort;
use Library\Domain\Entities\Book;
use Library\Domain\ValueObjects\Quantity;
use InvalidArgumentException;

/**
 * Update Book Service
 * 
 * Handles book metadata updates and copy management.
 * 
 * HU-03: Book management (admin)
 * - CA-03.3: Change total copies adjusts available, never < 0
 * - CA-03.4: Cover upload/removal
 * - CA-03.5: Logical deletion when no open loans
 */
final class UpdateBookService
{
    public function __construct(
        private BookRepositoryPort $bookRepository,
        private CoverStoragePort $coverStorage
    ) {}

    /**
     * Update book metadata
     * 
     * @param string $id
     * @param string $title
     * @param string $author
     * @param string $categoryId
     * @return array Book data
     * @throws BookUpdateException When update fails
     */
    public function updateBookMetadata(
        string $id,
        string $title,
        string $author,
        string $categoryId
    ): array {
        $book = $this->findActiveBook($id);
        
        // Validate inputs
        $this->validateMetadataInputs($title, $author, $categoryId);
        
        // Update metadata
        $updatedBook = $book->updateMetadata($title, $author, $categoryId);
        
        // Save changes
        $this->bookRepository->save($updatedBook);
        
        return $this->formatBook($updatedBook);
    }

    /**
     * Update book total copies
     * 
     * @param string $id
     * @param int $newTotal Must be ≥ 1
     * @return array Book data
     * @throws BookUpdateException When update fails
     */
    public function updateBookCopies(string $id, int $newTotal): array
    {
        $book = $this->findActiveBook($id);
        
        // Validate new total
        if ($newTotal < 1) {
            throw new BookUpdateException('Total copies must be at least 1');
        }
        
        try {
            // CA-03.3: Update total copies (never allows available < 0)
            $newTotalObj = Quantity::from($newTotal);
            $updatedBook = $book->updateTotalCopies($newTotalObj);
            
            // Save changes
            $this->bookRepository->save($updatedBook);
            
            return $this->formatBook($updatedBook);
        } catch (InvalidArgumentException $e) {
            // CA-03.3: Reject if would make available < 0
            throw new BookUpdateException($e->getMessage(), 422);
        }
    }

    /**
     * Update book cover
     * 
     * @param string $id
     * @param array|null $coverFile ['content' => string, 'mimeType' => string, 'filename' => string]
     * @return array Book data
     * @throws BookUpdateException When update fails
     */
    public function updateBookCover(string $id, ?array $coverFile = null): array
    {
        $book = $this->findActiveBook($id);
        
        $newCoverKey = null;
        $oldCoverKey = $book->coverKey();
        
        // Handle new cover upload
        if ($coverFile) {
            $newCoverKey = $this->handleCoverUpload($coverFile);
        }
        
        // Update book with new cover key (CA-03.4: null if no cover)
        $updatedBook = $book->setCover($newCoverKey);
        
        // Save changes first
        $this->bookRepository->save($updatedBook);
        
        // Clean up old cover if it was replaced
        if ($oldCoverKey && $oldCoverKey !== $newCoverKey) {
            try {
                $this->coverStorage->delete($oldCoverKey);
            } catch (\Exception $e) {
                // Log error but don't fail the operation
                error_log("Failed to delete old cover {$oldCoverKey}: " . $e->getMessage());
            }
        }
        
        return $this->formatBook($updatedBook);
    }

    /**
     * Delete book (logical deletion)
     * 
     * @param string $id
     * @throws BookUpdateException When deletion fails
     */
    public function deleteBook(string $id): void
    {
        $book = $this->findActiveBook($id);
        
        // CA-03.5: Cannot delete if has open loans
        if ($this->bookRepository->hasOpenLoans($id)) {
            throw new BookUpdateException('Cannot delete book with open loans', 409);
        }
        
        // Perform logical deletion
        $deletedBook = $book->delete();
        $this->bookRepository->save($deletedBook);
    }

    /**
     * Find active book by ID
     */
    private function findActiveBook(string $id): Book
    {
        $book = $this->bookRepository->findById($id);
        
        if (!$book || $book->isDeleted()) {
            throw new BookUpdateException('Book not found', 404);
        }
        
        return $book;
    }

    /**
     * Validate metadata inputs
     */
    private function validateMetadataInputs(string $title, string $author, string $categoryId): void
    {
        if (empty(trim($title))) {
            throw new BookUpdateException('Title is required');
        }
        
        if (empty(trim($author))) {
            throw new BookUpdateException('Author is required');
        }
        
        if (empty(trim($categoryId))) {
            throw new BookUpdateException('Category is required');
        }
    }

    /**
     * Handle cover file upload (same logic as CreateBookService)
     */
    private function handleCoverUpload(array $coverFile): string
    {
        $content = $coverFile['content'] ?? '';
        $mimeType = $coverFile['mimeType'] ?? '';
        $filename = $coverFile['filename'] ?? '';
        
        if (empty($content)) {
            throw new BookUpdateException('Cover file content is required');
        }
        
        // Validate file size (max 2MB - CA-03.4)
        if (strlen($content) > 2 * 1024 * 1024) {
            throw new BookUpdateException('Cover file too large (max 2MB)', 422);
        }
        
        // Validate MIME type (JPG/PNG only - CA-03.4)
        if (!in_array($mimeType, ['image/jpeg', 'image/jpg', 'image/png'])) {
            throw new BookUpdateException('Cover must be JPG or PNG', 422);
        }
        
        try {
            return $this->coverStorage->store($content, $mimeType, $filename);
        } catch (\Exception $e) {
            throw new BookUpdateException('Failed to store cover: ' . $e->getMessage(), 422);
        }
    }

    /**
     * Format book for API response
     */
    private function formatBook(Book $book): array
    {
        return [
            'id' => $book->id(),
            'title' => $book->title(),
            'author' => $book->author(),
            'isbn' => $book->isbn()->value(),
            'categoryId' => $book->categoryId(),
            'totalCopies' => $book->totalCopies()->value(),
            'availableCopies' => $book->availableCopies()->value(),
            'coverKey' => $book->coverKey(),
            'isActive' => $book->isActive()
        ];
    }
}

/**
 * Book Update Exception
 * 
 * Thrown when book update fails with specific error codes.
 */
class BookUpdateException extends \Exception
{
    private int $statusCode;

    public function __construct(string $message, int $statusCode = 400)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}