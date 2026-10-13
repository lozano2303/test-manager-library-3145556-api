<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class VerifyLendBooksCommand extends Command
{
    protected $signature = 'verify:lend-books';
    protected $description = 'Verify T-09: Lend books service';

    public function handle(): int
    {
        $this->info('=== T-09 Lend Books Service ===');
        
        try {
            // Setup fakes
            $bookRepo = new \Tests\Unit\Application\Fakes\FakeBookRepository();
            $memberRepo = new \Tests\Unit\Application\Fakes\FakeMemberRepository();
            $loanRepo = new \Tests\Unit\Application\Fakes\FakeLoanRepository();
            $clock = new \Tests\Unit\Domain\Services\FakeClock();
            
            $service = new \Library\Application\Services\LendBooksService(
                $bookRepo, $memberRepo, $loanRepo, $clock
            );
            
            // Create test data
            $member = \Library\Domain\Entities\Member::create('1', 'John Doe', '12345');
            $memberRepo->addMember($member);
            
            $book = \Library\Domain\Entities\Book::create(
                '1', 'Test Book', 'Author', 
                new \Library\Domain\ValueObjects\Isbn('1234567890123'),
                'cat1',
                new \Library\Domain\ValueObjects\Quantity(2),
                new \Library\Domain\ValueObjects\Quantity(2)
            );
            $bookRepo->addBook($book);
            
            // Test successful loan
            $loan = $service->lendBooks('1', ['1'], 'user1');
            
            if ($loan->getMemberId() !== '1') {
                throw new \Exception('Loan should have correct member');
            }
            
            $this->info('✅ T-09: Lend books service working');
            return 0;
            
        } catch (\Exception $e) {
            $this->error('❌ T-09 failed: ' . $e->getMessage());
            return 1;
        }
    }
}