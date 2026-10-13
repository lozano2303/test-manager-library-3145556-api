<?php

namespace Library\Application\Services;

use Library\Application\Ports\Outbound\LoanRepositoryPort;

class MostLoanedBooksReportService
{
    public function __construct(
        private LoanRepositoryPort $loanRepository
    ) {}

    public function getMostLoanedBooks(\DateTime $startDate, \DateTime $endDate): array
    {
        if ($startDate > $endDate) {
            throw new \InvalidArgumentException('Start date cannot be after end date');
        }

        // Basic implementation - returns empty array
        return [];
    }
}