<?php

namespace Tests\Unit\Library\Application\Services;

use PHPUnit\Framework\TestCase;
use Library\Application\Services\CreateLibrarianService;
use Library\Application\Services\LibrarianCreationException;
use Library\Adapters\Outbound\Persistence\FakeUserRepository;
use Library\Adapters\Outbound\Security\FakePasswordHasher;
use Library\Domain\Entities\User;

/**
 * CreateLibrarianService Test
 * 
 * Tests HU-02: Create librarians (admin only)
 * - CA-02.1: Admin creates user with name, password, role librarian
 * - CA-02.2: Username normalization, uniqueness → 409 if duplicate
 * - CA-02.3: Admin role via API → 422, password never exposed
 */
final class CreateLibrarianServiceTest extends TestCase
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
        
        // Seed existing users for conflict tests
        $this->userRepository->seed();
    }

    /**
     * @test
     * CA-02.1: Successfully create librarian with valid data
     */
    public function successfully_creates_librarian_with_valid_data(): void
    {
        // Act
        $result = $this->createLibrarianService->createLibrarian(
            'newlibrarian',
            'password123',
            'librarian'
        );
        
        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('username', $result);
        $this->assertArrayHasKey('role', $result);
        
        $this->assertEquals('newlibrarian', $result['username']);
        $this->assertEquals('librarian', $result['role']);
        
        // CA-02.3: Password hash should not be in response (RNF-3)
        $this->assertArrayNotHasKey('passwordHash', $result);
        $this->assertArrayNotHasKey('password', $result);
        
        // Verify user was saved in repository
        $user = $this->userRepository->findByUsername('newlibrarian');
        $this->assertNotNull($user);
        $this->assertEquals('librarian', $user->role());
    }

    /**
     * @test
     * CA-02.2: Username normalization (lowercase, trimmed)
     */
    public function username_normalization_works_correctly(): void
    {
        // Act - create with whitespace and uppercase
        $result = $this->createLibrarianService->createLibrarian(
            '  NewLibrarian  ',
            'password123',
            'librarian'
        );
        
        // Assert - should be normalized
        $this->assertEquals('newlibrarian', $result['username']);
        
        // Verify in repository
        $user = $this->userRepository->findByUsername('newlibrarian');
        $this->assertNotNull($user);
        $this->assertEquals('newlibrarian', $user->username());
    }

    /**
     * @test
     * CA-02.2: Duplicate username throws 409 exception
     */
    public function duplicate_username_throws_409_exception(): void
    {
        $this->expectException(LibrarianCreationException::class);
        $this->expectExceptionMessage('Username already exists');
        
        try {
            $this->createLibrarianService->createLibrarian(
                'admin', // Existing username from seed
                'password123',
                'librarian'
            );
        } catch (LibrarianCreationException $e) {
            $this->assertEquals(409, $e->getStatusCode());
            throw $e;
        }
    }

    /**
     * @test
     * CA-02.2: Case-insensitive duplicate detection
     */
    public function case_insensitive_duplicate_detection(): void
    {
        $this->expectException(LibrarianCreationException::class);
        
        try {
            $this->createLibrarianService->createLibrarian(
                'ADMIN', // Different case but same normalized username
                'password123',
                'librarian'
            );
        } catch (LibrarianCreationException $e) {
            $this->assertEquals(409, $e->getStatusCode());
            throw $e;
        }
    }

    /**
     * @test
     * CA-02.3: Admin role via API throws 422 exception
     */
    public function admin_role_via_api_throws_422_exception(): void
    {
        $this->expectException(LibrarianCreationException::class);
        $this->expectExceptionMessage('Admin role cannot be created via API');
        
        try {
            $this->createLibrarianService->createLibrarian(
                'newuser',
                'password123',
                'admin' // Should be rejected
            );
        } catch (LibrarianCreationException $e) {
            $this->assertEquals(422, $e->getStatusCode());
            throw $e;
        }
    }

    /**
     * @test
     * Invalid role (not librarian) throws 422 exception
     */
    public function invalid_role_throws_422_exception(): void
    {
        $this->expectException(LibrarianCreationException::class);
        $this->expectExceptionMessage('Only librarian role can be created via API');
        
        try {
            $this->createLibrarianService->createLibrarian(
                'newuser',
                'password123',
                'invalid_role'
            );
        } catch (LibrarianCreationException $e) {
            $this->assertEquals(422, $e->getStatusCode());
            throw $e;
        }
    }

    /**
     * @test
     * Empty username throws exception
     */
    public function empty_username_throws_exception(): void
    {
        $this->expectException(LibrarianCreationException::class);
        $this->expectExceptionMessage('Username is required');
        
        $this->createLibrarianService->createLibrarian(
            '',
            'password123',
            'librarian'
        );
    }

    /**
     * @test
     * Empty password throws exception
     */
    public function empty_password_throws_exception(): void
    {
        $this->expectException(LibrarianCreationException::class);
        $this->expectExceptionMessage('Password is required');
        
        $this->createLibrarianService->createLibrarian(
            'newuser',
            '',
            'librarian'
        );
    }

    /**
     * @test
     * Short password throws exception
     */
    public function short_password_throws_exception(): void
    {
        $this->expectException(LibrarianCreationException::class);
        $this->expectExceptionMessage('Password must be at least 6 characters');
        
        $this->createLibrarianService->createLibrarian(
            'newuser',
            '123', // Too short
            'librarian'
        );
    }

    /**
     * @test
     * Password is properly hashed
     */
    public function password_is_properly_hashed(): void
    {
        // Act
        $this->createLibrarianService->createLibrarian(
            'newuser',
            'plainpassword',
            'librarian'
        );
        
        // Assert - verify password was hashed
        $user = $this->userRepository->findByUsername('newuser');
        $this->assertNotNull($user);
        
        // FakePasswordHasher format: "hashed:" + original
        $this->assertEquals('hashed:plainpassword', $user->passwordHash());
    }

    /**
     * @test
     * User ID is generated correctly
     */
    public function user_id_is_generated_correctly(): void
    {
        // Act
        $result = $this->createLibrarianService->createLibrarian(
            'newuser',
            'password123',
            'librarian'
        );
        
        // Assert
        $this->assertNotEmpty($result['id']);
        $this->assertStringStartsWith('user-', $result['id']);
        
        // Verify user can be found by ID
        $user = $this->userRepository->findById($result['id']);
        $this->assertNotNull($user);
        $this->assertEquals('newuser', $user->username());
    }

    /**
     * @test
     * Only librarian role is accepted
     */
    public function only_librarian_role_is_accepted(): void
    {
        // Should succeed with librarian role
        $result = $this->createLibrarianService->createLibrarian(
            'gooduser',
            'password123',
            'librarian'
        );
        
        $this->assertEquals('librarian', $result['role']);
        
        // Should fail with any other role
        $invalidRoles = ['admin', 'user', 'customer', 'manager'];
        
        foreach ($invalidRoles as $role) {
            try {
                $this->createLibrarianService->createLibrarian(
                    'testuser' . $role,
                    'password123',
                    $role
                );
                $this->fail("Should have failed for role: {$role}");
            } catch (LibrarianCreationException $e) {
                $this->assertEquals(422, $e->getStatusCode());
            }
        }
    }
}