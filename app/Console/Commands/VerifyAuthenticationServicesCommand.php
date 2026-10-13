<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Verification Command for T-07: Authentication Services
 * 
 * Verifies HU-01 and HU-02 work against fakes
 */
class VerifyAuthenticationServicesCommand extends Command
{
    protected $signature = 'verify:auth-services';
    protected $description = 'Verify T-07: Authentication services with fakes';

    public function handle(): int
    {
        $this->info('=== T-07 Authentication Services Verification ===');
        
        try {
            // Test HU-01: Login Service
            $this->verifyLoginService();
            
            // Test HU-02: Create Librarian Service  
            $this->verifyCreateLibrarianService();
            
            $this->info('✅ T-07 Authentication Services: All verifications passed');
            return 0;
            
        } catch (\Exception $e) {
            $this->error('❌ T-07 Authentication Services: Verification failed');
            $this->error($e->getMessage());
            return 1;
        }
    }

    private function verifyLoginService(): void
    {
        $this->info('Testing HU-01: Login Service...');
        
        // Setup fakes
        $userRepo = new \Tests\Unit\Application\Fakes\FakeUserRepository();
        $passwordHasher = new \Tests\Unit\Application\Fakes\FakePasswordHasher();
        $tokenGenerator = new \Tests\Unit\Application\Fakes\FakeTokenGenerator();
        
        $loginService = new \Library\Application\Services\LoginService(
            $userRepo,
            $passwordHasher,
            $tokenGenerator
        );
        
        // Create test user
        $user = \Library\Domain\Entities\User::create('1', 'testuser', 'hashed_password123', 'librarian');
        $userRepo->addUser($user);
        
        // Test valid login
        $token = $loginService->login('testuser', 'password123');
        if (empty($token)) {
            throw new \Exception('Login should return token for valid credentials');
        }
        
        // Test invalid credentials
        try {
            $loginService->login('testuser', 'wrongpassword');
            throw new \Exception('Login should throw exception for invalid credentials');
        } catch (\Library\Application\Services\InvalidCredentialsException $e) {
            // Expected
        }
        
        $this->line('  ✓ HU-01: Login service working correctly');
    }

    private function verifyCreateLibrarianService(): void
    {
        $this->info('Testing HU-02: Create Librarian Service...');
        
        // Setup fakes
        $userRepo = new \Tests\Unit\Application\Fakes\FakeUserRepository();
        $passwordHasher = new \Tests\Unit\Application\Fakes\FakePasswordHasher();
        
        $service = new \Library\Application\Services\CreateLibrarianService(
            $userRepo,
            $passwordHasher
        );
        
        // Test successful creation
        $user = $service->createLibrarian('librarian1', 'password123', 'librarian');
        if ($user->getRole() !== 'librarian') {
            throw new \Exception('Created user should have librarian role');
        }
        
        // Test duplicate username
        try {
            $service->createLibrarian('librarian1', 'anotherpass', 'librarian');
            throw new \Exception('Should throw exception for duplicate username');
        } catch (\Library\Application\Services\DuplicateUsernameException $e) {
            // Expected
        }
        
        // Test admin role rejection
        try {
            $service->createLibrarian('admin1', 'password123', 'admin');
            throw new \Exception('Should reject admin role creation via API');
        } catch (\Library\Application\Services\InvalidRoleException $e) {
            // Expected
        }
        
        $this->line('  ✓ HU-02: Create librarian service working correctly');
    }
}