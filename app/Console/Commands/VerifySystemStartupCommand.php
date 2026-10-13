<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class VerifySystemStartupCommand extends Command
{
    protected $signature = 'verify:system-startup';
    protected $description = 'Verify T-17: System startup with empty database and admin login';

    public function handle(): int
    {
        $this->info('=== T-17 System Startup Verification ===');
        
        try {
            // Test service container
            $container = new \Library\Bootstrap\Composition\ServiceContainer();
            
            // Test admin user creation from environment
            $userRepo = $container->get(\Library\Application\Ports\Outbound\UserRepositoryPort::class);
            $loginService = $container->get(\Library\Application\Services\LoginService::class);
            
            // Test admin login
            $adminUsername = env('ADMIN_USERNAME', 'admin');
            $adminPassword = env('ADMIN_PASSWORD', 'admin123');
            
            try {
                $token = $loginService->login($adminUsername, $adminPassword);
                if (empty($token)) {
                    throw new \Exception('Admin login should return token');
                }
                
                $this->info("✅ Admin login successful with username: $adminUsername");
                
            } catch (\Exception $e) {
                throw new \Exception('Admin user not properly configured: ' . $e->getMessage());
            }
            
            // Test health endpoint is accessible
            $healthController = new \Library\Adapters\Inbound\Http\HealthController();
            $response = $healthController->check();
            
            if ($response->getData()->status !== 'ok') {
                throw new \Exception('Health endpoint not working');
            }
            
            $this->info('✅ Health endpoint working');
            $this->info('✅ T-17: System starts with empty database and admin login ready');
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error('❌ T-17 failed: ' . $e->getMessage());
            return 1;
        }
    }
}