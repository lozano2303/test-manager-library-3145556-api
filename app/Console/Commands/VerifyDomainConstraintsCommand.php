<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyDomainConstraintsCommand extends Command
{
    protected $signature = 'verify:domain-constraints';
    protected $description = 'Verify T-18: Domain constraints in database';

    public function handle(): int
    {
        $this->info('=== T-18 Domain Constraints Verification ===');
        
        try {
            // Test ck_book_total_positive constraint
            $this->testBookTotalPositiveConstraint();
            
            // Test ck_user_role constraint
            $this->testUserRoleConstraint();
            
            $this->info('✅ T-18: All domain constraints working correctly');
            return 0;
            
        } catch (\Exception $e) {
            $this->error('❌ T-18 failed: ' . $e->getMessage());
            return 1;
        }
    }

    private function testBookTotalPositiveConstraint(): void
    {
        $this->info('Testing ck_book_total_positive constraint...');
        
        try {
            // This should fail due to total_copies <= 0
            DB::statement("
                INSERT INTO library.book (id, title, author, isbn, category_id, total_copies, available_copies, deleted_at)
                VALUES (gen_random_uuid(), 'Test Book', 'Test Author', '1234567890123', 
                        (SELECT id FROM library.category LIMIT 1), 0, 0, NULL)
            ");
            
            throw new \Exception('Should have failed: total_copies = 0 violates ck_book_total_positive');
            
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'ck_book_total_positive')) {
                $this->line('  ✓ ck_book_total_positive constraint working');
            } else {
                throw new \Exception('Wrong constraint error: ' . $e->getMessage());
            }
        }
    }

    private function testUserRoleConstraint(): void
    {
        $this->info('Testing ck_user_role constraint...');
        
        try {
            // This should fail due to invalid role
            DB::statement("
                INSERT INTO library.\"user\" (id, username, password_hash, role)
                VALUES (gen_random_uuid(), 'testuser', 'password123', 'invalid_role')
            ");
            
            throw new \Exception('Should have failed: invalid_role violates ck_user_role');
            
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'ck_user_role')) {
                $this->line('  ✓ ck_user_role constraint working');
            } else {
                throw new \Exception('Wrong constraint error: ' . $e->getMessage());
            }
        }
        
        // Test valid role insertion works
        try {
            DB::statement("
                INSERT INTO library.\"user\" (id, username, password_hash, role)
                VALUES (gen_random_uuid(), 'validuser', 'password123', 'librarian')
            ");
            $this->line('  ✓ Valid role insertion works');
            
            // Cleanup
            DB::statement("DELETE FROM library.\"user\" WHERE username = 'validuser'");
            
        } catch (\Exception $e) {
            throw new \Exception('Valid role insertion should work: ' . $e->getMessage());
        }
    }
}