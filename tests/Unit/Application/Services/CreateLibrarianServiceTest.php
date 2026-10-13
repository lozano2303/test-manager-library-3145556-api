<?php

namespace Tests\Unit\Application\Services;

use PHPUnit\Framework\TestCase;
use Library\Application\Services\CreateLibrarianService;
use Library\Application\Services\DuplicateUsernameException;
use Library\Application\Services\InvalidRoleException;
use Tests\Unit\Application\Fakes\FakeUserRepository;
use Tests\Unit\Application\Fakes\FakePasswordHasher;

/**
 * Create Librarian Service Test
 * 
 * Tests HU-02: Admin creates librarians
 */
class CreateLibrarianServiceTest extends TestCase
{
    private CreateLibrarianService $createLibrarianService;
    private FakeUserRepository $userRepository;
    private FakePasswordHasher $passwordHasher;

    protected function setUp(): void
    {
        $this->userRepository = new FakeUserRepository();
        $this->passwordHasher = new FakePasswordHasher();
        
        $this->createLibrarianService = new CreateLibrarianService(
            $this->userRepository,
            $this->passwordHasher
        );
    }

    /** @test HU-02.1: Admin creates librarian with valid data */
    public function admin_creates_librarian_successfully(): void
    {
        // Act
        $user = $this->createLibrarianService->createLibrarian('john', 'password123', 'librarian');

        // Assert
        $this->assertEquals('john', $user->getUsername());
        $this->assertEquals('librarian', $user->getRole());
        $this->assertEquals('hashed_password123', $user->getPasswordHash());
        $this->assertEquals(1, $this->userRepository->getUserCount());
    }

    /** @test HU-02.2: Username is normalized and stored lowercase */
    public function username_is_normalized(): void
    {
        // Act
        $user = $this->createLibrarianService->createLibrarian('  JOHN  ', 'password123', 'librarian');

        // Assert
        $this->assertEquals('john', $user->getUsername());
        $this->assertTrue($this->userRepository->existsByUsername('john'));
    }

    /** @test HU-02.2: Duplicate username throws exception */
    public function duplicate_username_throws_exception(): void
    {
        // Arrange
        $this->createLibrarianService->createLibrarian('john', 'password123', 'librarian');

        // Assert & Act
        $this->expectException(DuplicateUsernameException::class);
        $this->expectExceptionMessage('Username already exists');
        
        $this->createLibrarianService->createLibrarian('john', 'anotherpass', 'librarian');
    }

    /** @test HU-02.2: Duplicate username case insensitive */
    public function duplicate_username_case_insensitive(): void
    {
        // Arrange
        $this->createLibrarianService->createLibrarian('john', 'password123', 'librarian');

        // Assert & Act
        $this->expectException(DuplicateUsernameException::class);
        
        $this->createLibrarianService->createLibrarian('JOHN', 'anotherpass', 'librarian');
    }

    /** @test HU-02.3: Admin role via API is rejected */
    public function admin_role_via_api_is_rejected(): void
    {
        // Assert & Act
        $this->expectException(InvalidRoleException::class);
        $this->expectExceptionMessage('Cannot create admin user via API');
        
        $this->createLibrarianService->createLibrarian('admin', 'password123', 'admin');
    }

    /** @test Invalid role is rejected */
    public function invalid_role_is_rejected(): void
    {
        // Assert & Act
        $this->expectException(InvalidRoleException::class);
        $this->expectExceptionMessage('Invalid role. Only librarian role allowed');
        
        $this->createLibrarianService->createLibrarian('user', 'password123', 'invalidrole');
    }

    /** @test HU-02.3: Password is hashed and never stored in plain text */
    public function password_is_hashed(): void
    {
        // Act
        $user = $this->createLibrarianService->createLibrarian('john', 'password123', 'librarian');

        // Assert
        $this->assertNotEquals('password123', $user->getPasswordHash());
        $this->assertEquals('hashed_password123', $user->getPasswordHash());
    }
}