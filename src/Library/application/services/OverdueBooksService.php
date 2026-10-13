<?php

namespace Library\Application\Services;

use Library\Application\Ports\Outbound\LoanRepositoryPort;
use Library\Domain\Services\Clock;

class OverdueBooksService
{
    public function __construct(
        private LoanRepositoryPort $loanRepository,
        private Clock $clock
    ) {}

    public function getOverdueBooks(): array
    {
        // Dummy implementation - returns empty for now
        return [];
    }
}