<?php

namespace Library\Application\Services;

use Library\Application\Ports\Outbound\LoanRepositoryPort;
use Library\Application\Ports\Outbound\BookRepositoryPort;
use Library\Domain\Services\Clock;

class ReturnBookService
{
    public function __construct(
        private LoanRepositoryPort $loanRepository,
        private BookRepositoryPort $bookRepository,
        private Clock $clock
    ) {}

    public function returnBook(string $loanId, string $itemId): void
    {
        $loan = $this->loanRepository->findById($loanId);
        if (!$loan) {
            throw new \InvalidArgumentException('Loan not found');
        }

        // CA-07.2: Cannot return already returned item
        $item = null;
        foreach ($loan->getItems() as $loanItem) {
            if ($loanItem->getId() === $itemId) {
                $item = $loanItem;
                break;
            }
        }

        if (!$item) {
            throw new \InvalidArgumentException('Item not found in loan');
        }

        if ($item->isReturned()) {
            throw new \InvalidArgumentException('Item already returned');
        }

        // Return item
        $loan->returnItem($itemId, $this->clock);
        
        // CA-07.1: Add 1 to available copies
        $book = $this->bookRepository->findById($item->getBookId());
        if ($book) {
            $book->restock();
            $this->bookRepository->save($book);
        }

        $this->loanRepository->save($loan);
    }
}