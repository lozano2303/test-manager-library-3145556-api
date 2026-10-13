<?php

namespace Library\Application\DTOs;

/**
 * Book Search Result DTO
 * 
 * Contains paginated search results with metadata
 */
class BookSearchResult
{
    public function __construct(
        public readonly array $books,
        public readonly int $total,
        public readonly int $page,
        public readonly int $size
    ) {}

    public function getTotalPages(): int
    {
        return (int) ceil($this->total / $this->size);
    }
}