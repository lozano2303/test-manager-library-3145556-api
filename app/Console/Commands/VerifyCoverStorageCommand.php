<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class VerifyCoverStorageCommand extends Command
{
    protected $signature = 'verify:cover-storage';
    protected $description = 'Verify T-15: Cover storage adapter';

    public function handle(): int
    {
        $this->info('=== T-15 Cover Storage Adapter ===');
        
        try {
            $storage = new \Library\Adapters\Outbound\Storage\DiskCoverStorage();
            
            // Test valid PNG (2MB)
            $validContent = str_repeat('x', 2 * 1024 * 1024); // Exactly 2MB
            $key = $storage->store($validContent, 'image/png', 2 * 1024 * 1024);
            
            if (empty($key)) {
                throw new \Exception('Should return opaque key for valid file');
            }
            
            // Test file too large (3MB should fail)
            try {
                $storage->store('content', 'image/png', 3 * 1024 * 1024);
                throw new \Exception('Should reject files over 2MB');
            } catch (\Library\Application\Services\FileTooLargeException $e) {
                // Expected
            }
            
            // Test invalid file type
            try {
                $storage->store('content', 'image/gif', 1024);
                throw new \Exception('Should reject non-JPG/PNG files');
            } catch (\Library\Application\Services\InvalidFileTypeException $e) {
                // Expected
            }
            
            $this->info('✅ T-15: Cover storage adapter working correctly');
            return 0;
            
        } catch (\Exception $e) {
            $this->error('❌ T-15 failed: ' . $e->getMessage());
            return 1;
        }
    }
}