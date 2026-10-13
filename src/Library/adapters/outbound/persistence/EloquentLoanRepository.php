<?php

namespace Library\Adapters\Outbound\Persistence;

use Library\Application\Ports\Outbound\LoanRepositoryPort;
use Library\Domain\Entities\Loan;

class EloquentLoanRepository implements LoanRepositoryPort
{
    public function save(Loan $loan): void
    {
        // TODO: Version witness implementation for RNF-2
    }

    public function findById(string $id): ?Loan
    {
        return null;
    }

    public function countUnreturnedByMember(string $memberId): int
    {
        return 0;
    }

    public function hasOverdueByMember(string $memberId): bool
    {
        return false;
    }

    public function nextId(): string
    {
        return uniqid();
    }
}