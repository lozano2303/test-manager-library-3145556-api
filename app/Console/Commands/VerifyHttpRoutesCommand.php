<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class VerifyHttpRoutesCommand extends Command
{
    protected $signature = 'verify:http-routes';
    protected $description = 'Verify T-16: HTTP routes and controllers';

    public function handle(): int
    {
        $this->info('=== T-16 HTTP Routes Verification ===');
        
        try {
            // Check if routes are registered
            $routes = collect(\Route::getRoutes())->map(function ($route) {
                return [
                    'method' => implode('|', $route->methods()),
                    'uri' => $route->uri(),
                    'action' => $route->getActionName()
                ];
            });
            
            $expectedRoutes = [
                'GET|HEAD api/health',
                'POST api/auth/login', 
                'POST api/users/librarians',
                'GET|HEAD api/books',
                'POST api/books',
                'GET|HEAD api/books/{id}',
                'DELETE api/books/{id}',
                'POST api/loans',
                'POST api/loans/{loanId}/items/{itemId}/return',
                'GET|HEAD api/loans/overdue'
            ];
            
            $foundRoutes = $routes->pluck('method')->combine($routes->pluck('uri'))->map(function ($uri, $method) {
                return $method . ' ' . $uri;
            })->values();
            
            foreach ($expectedRoutes as $expected) {
                if (!$foundRoutes->contains(function ($route) use ($expected) {
                    return str_contains($route, str_replace('|HEAD', '', $expected));
                })) {
                    throw new \Exception("Route not found: $expected");
                }
            }
            
            $this->info('✅ T-16: All HTTP routes registered correctly');
            $this->line('Routes found:');
            foreach ($foundRoutes->take(10) as $route) {
                $this->line("  - $route");
            }
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error('❌ T-16 failed: ' . $e->getMessage());
            return 1;
        }
    }
}