<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * T-18: Add Domain-Only Constraints to Database
 * 
 * Enforces business rules at database level for data integrity
 */
return new class extends Migration
{
    public function up()
    {
        // ck_book_total_positive: Total copies must be positive
        DB::statement('ALTER TABLE library.book ADD CONSTRAINT ck_book_total_positive CHECK (total_copies > 0)');
        
        // ck_user_role: Valid user roles only
        DB::statement("ALTER TABLE library.\"user\" ADD CONSTRAINT ck_user_role CHECK (role IN ('admin', 'librarian'))");
        
        // Additional constraints for loan business rules
        DB::statement('ALTER TABLE library.loan_item ADD CONSTRAINT ck_loan_item_due_after_loaned CHECK (due_at > loaned_at)');
        
        // Ensure returned items have valid return date
        DB::statement('ALTER TABLE library.loan_item ADD CONSTRAINT ck_loan_item_return_after_loaned CHECK (returned_at IS NULL OR returned_at >= loaned_at)');
    }

    public function down()
    {
        DB::statement('ALTER TABLE library.book DROP CONSTRAINT IF EXISTS ck_book_total_positive');
        DB::statement('ALTER TABLE library."user" DROP CONSTRAINT IF EXISTS ck_user_role');
        DB::statement('ALTER TABLE library.loan_item DROP CONSTRAINT IF EXISTS ck_loan_item_due_after_loaned');
        DB::statement('ALTER TABLE library.loan_item DROP CONSTRAINT IF EXISTS ck_loan_item_return_after_loaned');
    }
};