<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Migration: Create Library Schema
 * 
 * Creates the complete library schema with 6 tables following the hexagonal architecture
 * and data-model.md specifications. All tables use UUIDs and no DEFAULT values.
 * 
 * Tables created:
 * - categories (with 5 fixed categories seeded)
 * - books
 * - members  
 * - users
 * - loans
 * - loan_items
 * 
 * Follows constitution.md Article V: Schema is owned by migrations
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Enable UUID extension
        DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');
        
        // Set schema
        DB::statement('CREATE SCHEMA IF NOT EXISTS library');
        DB::statement('SET search_path TO library,public');

        // 1. CATEGORY table - semilla de solo lectura
        Schema::create('library.category', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 120)->unique();
            // No timestamps - siguiendo data-model.md
        });

        // 2. BOOK table  
        Schema::create('library.book', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 250);
            $table->string('author', 200);
            $table->char('isbn', 13); // Solo dígitos
            $table->uuid('category_id');
            $table->integer('total_copies');
            $table->integer('available_copies');
            $table->string('cover_key', 512)->nullable();
            $table->timestampTz('deleted_at')->nullable(); // Baja lógica
            
            // Foreign keys
            $table->foreign('category_id')->references('id')->on('library.category')->onDelete('restrict');
        });

        // After creating book table, add partial unique index for ISBN (active books only)
        DB::statement('CREATE UNIQUE INDEX unique_isbn_active ON library.book (isbn) WHERE deleted_at IS NULL');
        
        // Add CHECK constraint for available_copies range
        DB::statement('ALTER TABLE library.book ADD CONSTRAINT ck_book_available_range CHECK (available_copies >= 0 AND available_copies <= total_copies)');
        });

        // 3. MEMBER table
        Schema::create('library.member', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('full_name', 200);
            $table->string('document_number', 30)->unique();
        });

        // 4. USER table
        Schema::create('library.user', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('username', 120)->unique();
            $table->string('password_hash', 512);
            $table->string('role', 40);
        });

        // 5. LOAN table
        Schema::create('library.loan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('member_id');
            $table->uuid('loaned_by_user_id');
            $table->timestampTz('loaned_at');
            
            // Foreign keys
            $table->foreign('member_id')->references('id')->on('library.member')->onDelete('restrict');
            $table->foreign('loaned_by_user_id')->references('id')->on('library.user')->onDelete('restrict');
        });

        // 6. LOAN_ITEM table
        Schema::create('library.loan_item', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('loan_id');
            $table->uuid('book_id');
            $table->string('book_title', 250); // Congelado
            $table->char('isbn', 13); // Congelado
            $table->string('category_name', 120); // Congelado, SIN FK a propósito
            $table->timestampTz('due_at');
            $table->timestampTz('returned_at')->nullable();
            
            // Foreign keys
            $table->foreign('loan_id')->references('id')->on('library.loan')->onDelete('cascade');
            $table->foreign('book_id')->references('id')->on('library.book')->onDelete('restrict');
            
            // Unique constraints - No se repite mismo libro en un préstamo
            $table->unique(['loan_id', 'book_id']);
        });

        // Add indexes for access patterns (data-model.md §6)
        DB::statement('CREATE INDEX idx_book_category_title ON library.book (category_id, title) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_loan_loaned_at ON library.loan (loaned_at)');  
        DB::statement('CREATE INDEX idx_loan_item_book_id ON library.loan_item (book_id)');
        DB::statement('CREATE INDEX idx_loan_item_overdue ON library.loan_item (due_at) WHERE returned_at IS NULL');

        // Seed the 5 fixed categories with literal UUIDs (data-model.md §9)
        DB::table('library.category')->insert([
            ['id' => '11111111-1111-4111-8111-111111111111', 'name' => 'Novela'],
            ['id' => '22222222-2222-4222-8222-222222222222', 'name' => 'Ciencia'],
            ['id' => '33333333-3333-4333-8333-333333333333', 'name' => 'Tecnología'],
            ['id' => '44444444-4444-4444-8444-444444444444', 'name' => 'Historia'],
            ['id' => '55555555-5555-4555-8555-555555555555', 'name' => 'Infantil'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('library.loan_item');
        Schema::dropIfExists('library.loan');
        Schema::dropIfExists('library.user');
        Schema::dropIfExists('library.member');
        Schema::dropIfExists('library.book');
        Schema::dropIfExists('library.category');
        
        // Drop schema
        DB::statement('DROP SCHEMA IF EXISTS library CASCADE');
    }
};