<?php

namespace Library\Bootstrap\Composition;

use Library\Application\Ports\Outbound\UserRepositoryPort;
use Library\Application\Ports\Outbound\BookRepositoryPort;
use Library\Application\Ports\Outbound\LoanRepositoryPort;
use Library\Application\Ports\Outbound\MemberRepositoryPort;
use Library\Application\Ports\Outbound\PasswordHasherPort;
use Library\Application\Ports\Outbound\TokenGeneratorPort;
use Library\Application\Ports\Outbound\CoverStoragePort;

use Library\Application\Services\LoginService;
use Library\Application\Services\CreateLibrarianService;
use Library\Application\Services\CreateBookService;
use Library\Application\Services\SearchBooksService;
use Library\Application\Services\LendBooksService;

use Library\Adapters\Outbound\Persistence\EloquentBookRepository;
use Library\Adapters\Outbound\Persistence\EloquentLoanRepository;
use Library\Adapters\Outbound\Security\LaravelPasswordHasher;
use Library\Adapters\Outbound\Security\JwtTokenGenerator;
use Library\Adapters\Outbound\Storage\DiskCoverStorage;

use Library\Domain\Services\Clock;
use Library\Domain\Services\SystemClock;

/**
 * Dependency Injection Container for Library System
 * 
 * Implements hexagonal architecture composition
 */
class ServiceContainer
{
    private array $services = [];

    public function __construct()
    {
        $this->registerServices();
    }

    private function registerServices(): void
    {
        // Domain services
        $this->services[Clock::class] = fn() => new SystemClock();

        // Outbound ports (adapters)
        $this->services[PasswordHasherPort::class] = fn() => new LaravelPasswordHasher();
        $this->services[TokenGeneratorPort::class] = fn() => new JwtTokenGenerator();
        $this->services[BookRepositoryPort::class] = fn() => new EloquentBookRepository();
        $this->services[LoanRepositoryPort::class] = fn() => new EloquentLoanRepository();
        $this->services[CoverStoragePort::class] = fn() => new DiskCoverStorage();

        // User repository with initial admin support
        $this->services[UserRepositoryPort::class] = fn() => new class implements UserRepositoryPort {
            private array $users = [];
            private int $nextId = 1;

            public function __construct() {
                // Create initial admin user from environment
                $adminUsername = env('ADMIN_USERNAME', 'admin');
                $adminPassword = env('ADMIN_PASSWORD', 'admin123');
                
                $hasher = new LaravelPasswordHasher();
                $admin = \Library\Domain\Entities\User::create(
                    'admin-1',
                    $adminUsername,
                    $hasher->hash($adminPassword),
                    'admin'
                );
                $this->users['admin-1'] = $admin;
            }

            public function save(\Library\Domain\Entities\User $user): void {
                $this->users[$user->getId()] = $user;
            }

            public function findByUsername(string $username): ?\Library\Domain\Entities\User {
                foreach ($this->users as $user) {
                    if ($user->getUsername() === $username) return $user;
                }
                return null;
            }

            public function findById(string $id): ?\Library\Domain\Entities\User {
                return $this->users[$id] ?? null;
            }

            public function existsByUsername(string $username): bool {
                return $this->findByUsername($username) !== null;
            }

            public function nextId(): string {
                return 'user-' . $this->nextId++;
            }
        };

        // Application services
        $this->services[LoginService::class] = fn() => new LoginService(
            $this->get(UserRepositoryPort::class),
            $this->get(PasswordHasherPort::class),
            $this->get(TokenGeneratorPort::class)
        );

        $this->services[CreateLibrarianService::class] = fn() => new CreateLibrarianService(
            $this->get(UserRepositoryPort::class),
            $this->get(PasswordHasherPort::class)
        );

        $this->services[CreateBookService::class] = fn() => new CreateBookService(
            $this->get(BookRepositoryPort::class),
            $this->get(CoverStoragePort::class)
        );

        $this->services[SearchBooksService::class] = fn() => new SearchBooksService(
            $this->get(BookRepositoryPort::class)
        );
    }

    public function get(string $serviceClass)
    {
        if (!isset($this->services[$serviceClass])) {
            throw new \InvalidArgumentException("Service not registered: $serviceClass");
        }

        if (is_callable($this->services[$serviceClass])) {
            $this->services[$serviceClass] = $this->services[$serviceClass]();
        }

        return $this->services[$serviceClass];
    }
}