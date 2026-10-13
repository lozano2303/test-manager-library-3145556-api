<?php

namespace Library\Application\Services;

use Library\Application\Ports\Outbound\BookRepositoryPort;
use Library\Application\Ports\Outbound\MemberRepositoryPort;
use Library\Application\Ports\Outbound\LoanRepositoryPort;
use Library\Domain\Entities\Loan;
use Library\Domain\Services\Clock;

class LendBooksService
{
    public function __construct(
        private BookRepositoryPort $bookRepository,
        private MemberRepositoryPort $memberRepository,
        private LoanRepositoryPort $loanRepository,
        private Clock $clock
    ) {}

    public function lendBooks(string $memberId, array $bookIds, string $userId): Loan
    {
        // Validaciones HU-06
        if (count($bookIds) > 3) {
            throw new \InvalidArgumentException('Maximum 3 books per loan');
        }

        $member = $this->memberRepository->findById($memberId);
        if (!$member) {
            throw new \InvalidArgumentException('Member not found');
        }

        // CA-06.4: Max 3 books unreturned
        if ($this->loanRepository->countUnreturnedByMember($memberId) + count($bookIds) > 3) {
            throw new \InvalidArgumentException('Member cannot have more than 3 unreturned books');
        }

        // CA-06.5: No overdue books
        if ($this->loanRepository->hasOverdueByMember($memberId)) {
            throw new \InvalidArgumentException('Member has overdue books');
        }

        // Validar libros disponibles
        $books = [];
        foreach ($bookIds as $bookId) {
            $book = $this->bookRepository->findById($bookId);
            if (!$book || $book->isDeleted()) {
                throw new \InvalidArgumentException('Book not found');
            }
            if ($book->getAvailableCopies()->getValue() === 0) {
                throw new \InvalidArgumentException('No available copies');
            }
            $books[] = $book;
        }

        // CA-06.2: No duplicate books
        if (count(array_unique($bookIds)) !== count($bookIds)) {
            throw new \InvalidArgumentException('Cannot repeat books in same loan');
        }

        // Crear préstamo
        $loanId = $this->loanRepository->nextId();
        $loan = Loan::create($loanId, $memberId, $userId, $this->clock);

        // Agregar items y restar disponibles
        foreach ($books as $book) {
            $loan->addItem($book->getId(), $book->getTitle(), $book->getIsbn()->getValue(), 'Category Name');
            $book->withdraw();
            $this->bookRepository->save($book);
        }

        $this->loanRepository->save($loan);
        return $loan;
    }
}