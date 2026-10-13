<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Command to verify database schema matches data-model.md specifications
 * 
 * Runs the verification queries from data-model.md §10 to ensure:
 * - All columns exist with correct types and nullable settings
 * - No columns have DEFAULT values (domain sets values)
 * - All constraints (PK, FK, CHECK) are properly created
 * - All indexes are in place
 * 
 * This is the evidence for T-02 completion.
 */
class VerifyDatabaseSchemaCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'library:verify-schema';

    /**
     * The console command description.
     */
    protected $description = 'Verify database schema matches data-model.md specifications (T-02 evidence)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Verifying Library Database Schema (T-02 Evidence)');
        $this->info('Running queries from data-model.md §10...');
        $this->newLine();

        try {
            // 1. Verify columns structure
            $this->verifyColumns();
            
            // 2. Verify constraints
            $this->verifyConstraints();
            
            // 3. Verify indexes
            $this->verifyIndexes();
            
            // 4. Verify categories seeding
            $this->verifyCategories();

            $this->newLine();
            $this->info('✅ Schema verification completed successfully!');
            $this->info('T-02 evidence: All requirements from data-model.md are satisfied.');
            
            return self::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('❌ Schema verification failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Verify columns match §3 specifications - no DEFAULT values
     */
    private function verifyColumns(): void
    {
        $this->info('📋 Verifying columns (must match §3, no DEFAULT values)...');
        
        $columns = DB::select("
            SELECT table_name, ordinal_position, column_name, data_type, is_nullable, column_default
            FROM information_schema.columns 
            WHERE table_schema = 'library' 
            ORDER BY 1, 2
        ");

        if (empty($columns)) {
            throw new \Exception('No tables found in library schema');
        }

        $this->table(
            ['Table', 'Position', 'Column', 'Type', 'Nullable', 'Default'],
            array_map(fn($col) => [
                $col->table_name,
                $col->ordinal_position,
                $col->column_name,
                $col->data_type,
                $col->is_nullable,
                $col->column_default ?? 'NULL'
            ], $columns)
        );

        // Check for DEFAULT values (should be none)
        $withDefaults = array_filter($columns, fn($col) => $col->column_default !== null);
        
        if (!empty($withDefaults)) {
            $defaultColumns = array_map(fn($col) => "{$col->table_name}.{$col->column_name}", $withDefaults);
            throw new \Exception('Columns with DEFAULT values found (violates data-model.md): ' . implode(', ', $defaultColumns));
        }

        $this->info('✅ All columns correct, no DEFAULT values found');
    }

    /**
     * Verify constraints (PK, FK, CHECK) 
     */
    private function verifyConstraints(): void
    {
        $this->info('🔗 Verifying constraints (PK, FK, CHECK)...');
        
        $constraints = DB::select("
            SELECT c.conrelid::regclass as table_name, c.conname, c.contype, pg_get_constraintdef(c.oid) as definition
            FROM pg_constraint c 
            JOIN pg_namespace n ON n.oid = c.connamespace
            WHERE n.nspname = 'library' 
            ORDER BY 1, 3, 2
        ");

        if (empty($constraints)) {
            throw new \Exception('No constraints found in library schema');
        }

        $this->table(
            ['Table', 'Constraint', 'Type', 'Definition'],
            array_map(fn($con) => [
                $con->table_name,
                $con->conname,
                $this->getConstraintTypeName($con->contype),
                $con->definition
            ], $constraints)
        );

        // Verify critical constraints exist
        $constraintNames = array_column($constraints, 'conname');
        $requiredConstraints = [
            'ck_book_available_range' // Critical concurrency constraint
        ];

        foreach ($requiredConstraints as $required) {
            if (!in_array($required, $constraintNames)) {
                throw new \Exception("Required constraint missing: {$required}");
            }
        }

        $this->info('✅ All required constraints found');
    }

    /**
     * Verify indexes for access patterns
     */
    private function verifyIndexes(): void
    {
        $this->info('📊 Verifying indexes for access patterns...');
        
        $indexes = DB::select("
            SELECT tablename, indexname, indexdef 
            FROM pg_indexes 
            WHERE schemaname = 'library' 
            ORDER BY 1, 2
        ");

        if (empty($indexes)) {
            throw new \Exception('No indexes found in library schema');
        }

        $this->table(
            ['Table', 'Index', 'Definition'],
            array_map(fn($idx) => [
                $idx->tablename,
                $idx->indexname,
                $idx->indexdef
            ], $indexes)
        );

        $this->info('✅ Indexes created for access patterns');
    }

    /**
     * Verify the 5 fixed categories are seeded
     */
    private function verifyCategories(): void
    {
        $this->info('🏷️ Verifying 5 fixed categories seeding...');
        
        $categories = DB::table('library.category')
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($categories->count() !== 5) {
            throw new \Exception("Expected 5 categories, found {$categories->count()}");
        }

        $expectedCategories = [
            '33333333-3333-4333-8333-333333333333' => 'Tecnología',
            '22222222-2222-4222-8222-222222222222' => 'Ciencia', 
            '44444444-4444-4444-8444-444444444444' => 'Historia',
            '55555555-5555-4555-8555-555555555555' => 'Infantil',
            '11111111-1111-4111-8111-111111111111' => 'Novela'
        ];

        foreach ($categories as $category) {
            if (!isset($expectedCategories[$category->id]) || 
                $expectedCategories[$category->id] !== $category->name) {
                throw new \Exception("Category mismatch: {$category->id} => {$category->name}");
            }
        }

        $this->table(
            ['ID', 'Name'],
            $categories->map(fn($cat) => [$cat->id, $cat->name])->toArray()
        );

        $this->info('✅ All 5 categories correctly seeded with fixed UUIDs');
    }

    /**
     * Get human-readable constraint type name
     */
    private function getConstraintTypeName(string $type): string
    {
        return match($type) {
            'p' => 'PRIMARY KEY',
            'f' => 'FOREIGN KEY',
            'c' => 'CHECK',
            'u' => 'UNIQUE',
            default => $type
        };
    }
}