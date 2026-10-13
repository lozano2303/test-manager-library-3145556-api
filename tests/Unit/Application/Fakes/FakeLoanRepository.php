<?php

namespace Tests\Unit\Application\Fakes;

use Library\Application\Ports\Outbound\LoanRepositoryPort;
use Library\Domain\Entities\Loan;

class FakeLoanRepository implements LoanRepositoryPort
{
    private array $loans = [];
    private array $memberUnreturnedCounts = [];
    private array $memberOverdue = [];
    private int $nextId = 1;

    public function save(Loan $loan): void
    {
        $this->loans[$loan->getId()] = $loan;
    }

    public function findById(string $id): ?Loan
    {
        return $this->loans[$id] ?? null;
    }

    public function countUnreturnedByMember(string $memberId): int
    {
        return $this->memberUnreturnedCounts[$memberId] ?? 0;
    }

    public function hasOverdueByMember(string $memberId): bool
    {
        return $this->memberOverdue[$memberId] ?? false;
    }

    public function nextId(): string
    {
        return (string) $this->nextId++;
    }

    public function setUnreturnedCount(string $memberId, int $count): void
    {
        $this->memberUnreturnedCounts[$memberId] = $count;
    }

    public function setHasOverdue(string $memberId, bool $hasOverdue): void
    {
        $this->memberOverdue[$memberId] = $hasOverdue;
    }

    public function reset(): void
    {
        $this->loans = [];
        $this->memberUnreturnedCounts = [];
        $this->memberOverdue = [];
        $this->nextId = 1;
    }
}