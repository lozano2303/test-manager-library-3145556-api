<?php

namespace Library\Application\Ports\Outbound;

use Library\Domain\Entities\Loan;

interface LoanRepositoryPort
{
    public function save(Loan $loan): void;
    public function findById(string $id): ?Loan;
    public function countUnreturnedByMember(string $memberId): int;
    public function hasOverdueByMember(string $memberId): bool;
    public function nextId(): string;
}