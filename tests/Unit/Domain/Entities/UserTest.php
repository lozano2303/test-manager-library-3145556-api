<?php

namespace Tests\Unit\Domain\Entities;

use Library\Domain\Entities\User;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

/**
 * Test for User Entity
 * 
 * Evidence for T-06: rol `admin` por API rechazado (admin role rejected via API)
 */
class UserTest extends TestCase
{
    public function testUserCreation(): void
    {
        $user = User::create(
            id: 'user-123',
            username: 'librarian1',
            passwordHash: 'hashed_password_123',
            role: 'librarian'
        );

        $this->assertEquals('user-123', $user->id());
        $this->assertEquals('librarian1', $user->username());
        $this->assertEquals('hashed_password_123', $user->passwordHash());
        $this->assertEquals('librarian', $user->role());
        $this->assertFalse($user->isAdmin());
        $this->assertTrue($user->isLibrarian());
    }

    public function testAdminUserCreation(): void
    {
        $user = User::create(
            id: 'user-123',
            username: 'admin1',
            passwordHash: 'hashed_password_123',
            role: 'admin'
        );

        $this->assertEquals('admin', $user->role());
        $this->assertTrue($user->isAdmin());
        $this->assertFalse($user->isLibrarian());
        $this->assertTrue($user->canPerformAdminOperations());
        $this->assertTrue($user->canPerformLibrarianOperations()); // Admin can do librarian operations too
    }

    /**
     * T-06 Evidence: rol `admin` por API rechazado
     */
    public function testAdminRoleRejectedViaApi(): void
    {
        // Creating admin via API should fail
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Role "admin" cannot be created via API. Allowed roles: librarian');

        User::createViaApi(
            id: 'user-123',
            username: 'admin1',
            passwordHash: 'hashed_password_123',
            role: 'admin'  // This should be rejected
        );
    }

    /**
     * T-06 Evidence: Librarian role allowed via API
     */
    public function testLibrarianRoleAllowedViaApi(): void
    {
        $user = User::createViaApi(
            id: 'user-123',
            username: 'librarian1',
            passwordHash: 'hashed_password_123',
            role: 'librarian'  // This should be allowed
        );

        $this->assertEquals('librarian', $user->role());
        $this->assertTrue($user->isLibrarian());
        $this->assertFalse($user->isAdmin());
    }

    public function testInvalidRoleRejectedViaApi(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Role "invalid" cannot be created via API');

        User::createViaApi(
            id: 'user-123',
            username: 'user1',
            passwordHash: 'hashed_password_123',
            role: 'invalid'  // Invalid role
        );
    }

    public function testUsernameNormalization(): void
    {
        $testCases = [
            // [input, expected]
            ['LibrarianUser', 'librarianuser'],           // Uppercase to lowercase
            ['  librarian1  ', 'librarian1'],            // Trim spaces
            ['user name', 'username'],                    // Remove internal spaces
            ['User.Name_123', 'user.name_123'],          // Preserve dots and underscores
            ['user-name', 'user-name'],                   // Preserve hyphens
        ];

        foreach ($testCases as [$input, $expected]) {
            $user = User::create('user-' . uniqid(), $input, 'hash', 'librarian');
            $this->assertEquals($expected, $user->username(), "Failed for input: '$input'");
        }
    }

    public function testUpdatePassword(): void
    {
        $user = User::create('user-123', 'librarian1', 'old_hash', 'librarian');
        
        $updatedUser = $user->updatePassword('new_hash');

        // Original unchanged
        $this->assertEquals('old_hash', $user->passwordHash());

        // Updated version has new hash
        $this->assertEquals('new_hash', $updatedUser->passwordHash());
        $this->assertEquals($user->id(), $updatedUser->id());
        $this->assertEquals($user->username(), $updatedUser->username());
        $this->assertEquals($user->role(), $updatedUser->role());
    }

    public function testUpdateRole(): void
    {
        $user = User::create('user-123', 'user1', 'hash', 'librarian');
        
        $updatedUser = $user->updateRole('admin');

        // Original unchanged
        $this->assertEquals('librarian', $user->role());

        // Updated version has new role
        $this->assertEquals('admin', $updatedUser->role());
        $this->assertTrue($updatedUser->isAdmin());
    }

    public function testRolePermissions(): void
    {
        $admin = User::create('user-1', 'admin1', 'hash', 'admin');
        $librarian = User::create('user-2', 'librarian1', 'hash', 'librarian');

        // Admin permissions
        $this->assertTrue($admin->hasRole('admin'));
        $this->assertFalse($admin->hasRole('librarian'));
        $this->assertTrue($admin->canPerformAdminOperations());
        $this->assertTrue($admin->canPerformLibrarianOperations()); // Admin can do librarian work

        // Librarian permissions
        $this->assertFalse($librarian->hasRole('admin'));
        $this->assertTrue($librarian->hasRole('librarian'));
        $this->assertFalse($librarian->canPerformAdminOperations());
        $this->assertTrue($librarian->canPerformLibrarianOperations());
    }

    public function testValidRoles(): void
    {
        $validRoles = User::getValidRoles();
        $apiRoles = User::getApiAllowedRoles();

        $this->assertEquals(['admin', 'librarian'], $validRoles);
        $this->assertEquals(['librarian'], $apiRoles); // Admin excluded from API
    }

    public function testInvalidRoleValidation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid role "invalid". Valid roles: admin, librarian');

        User::create('user-123', 'user1', 'hash', 'invalid');
    }

    public function testEmptyUsernameValidation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Username cannot be empty');

        User::create('user-123', '   ', 'hash', 'librarian');
    }

    public function testShortUsernameValidation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Username must be at least 3 characters long');

        User::create('user-123', 'ab', 'hash', 'librarian'); // Only 2 characters
    }

    public function testLongUsernameValidation(): void
    {
        $longUsername = str_repeat('a', 121); // 121 characters (over limit)

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Username cannot exceed 120 characters');

        User::create('user-123', $longUsername, 'hash', 'librarian');
    }

    public function testInvalidUsernameCharacters(): void
    {
        $invalidUsernames = [
            'user@name',    // Contains @
            'user name',    // Contains space (after normalization removal, should still be invalid format)
            'user#name',    // Contains #
            'user$name',    // Contains $
            'user%name',    // Contains %
        ];

        foreach ($invalidUsernames as $invalid) {
            try {
                User::create('user-123', $invalid, 'hash', 'librarian');
                $this->fail("Expected InvalidArgumentException for username: $invalid");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContains('Username contains invalid characters', $e->getMessage());
            }
        }
    }

    public function testValidUsernameCharacters(): void
    {
        $validUsernames = [
            'user123',          // Alphanumeric
            'user.name',        // With dot
            'user_name',        // With underscore  
            'user-name',        // With dash
            'a.b_c-123',        // Mixed valid characters
        ];

        foreach ($validUsernames as $valid) {
            $user = User::create('user-' . uniqid(), $valid, 'hash', 'librarian');
            $this->assertNotNull($user);
        }
    }

    public function testEmptyPasswordHashValidation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Password hash cannot be empty');

        User::create('user-123', 'username', '', 'librarian'); // Empty password hash
    }

    public function testUserEquality(): void
    {
        $user1 = User::create('user-123', 'username1', 'hash1', 'librarian');
        $user2 = User::create('user-123', 'username2', 'hash2', 'admin'); // Same ID
        $user3 = User::create('user-456', 'username1', 'hash1', 'librarian'); // Different ID

        $this->assertTrue($user1->equals($user2)); // Same ID
        $this->assertFalse($user1->equals($user3)); // Different ID
    }

    public function testToLogArray(): void
    {
        $user = User::create('user-123', 'librarian1', 'secret_hash', 'librarian');
        
        $logArray = $user->toLogArray();
        
        $this->assertEquals([
            'id' => 'user-123',
            'username' => 'librarian1',
            'role' => 'librarian'
        ], $logArray);
        
        // Verify password hash is NOT included in log array (security)
        $this->assertArrayNotHasKey('passwordHash', $logArray);
        $this->assertArrayNotHasKey('password', $logArray);
    }

    public function testFromPersistence(): void
    {
        $user = User::fromPersistence(
            id: 'user-123',
            username: 'librarian1', // Already normalized
            passwordHash: 'hash',
            role: 'librarian'
        );

        $this->assertEquals('user-123', $user->id());
        $this->assertEquals('librarian1', $user->username());
        $this->assertEquals('hash', $user->passwordHash());
        $this->assertEquals('librarian', $user->role());
    }

    /**
     * Comprehensive test for T-06 evidence: role validation
     */
    public function testT06EvidenceComprehensive(): void
    {
        // T-06 Evidence: rol `admin` por API rechazado
        try {
            User::createViaApi('user-1', 'admin1', 'hash', 'admin');
            $this->fail('Expected admin role to be rejected via API');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContains('Role "admin" cannot be created via API', $e->getMessage()); // ✓
        }

        // Librarian role should be allowed via API
        $librarian = User::createViaApi('user-2', 'lib1', 'hash', 'librarian');
        $this->assertEquals('librarian', $librarian->role()); // ✓

        // Admin can still be created via system method
        $admin = User::create('user-3', 'admin1', 'hash', 'admin');
        $this->assertEquals('admin', $admin->role()); // ✓

        // Invalid roles rejected everywhere
        try {
            User::create('user-4', 'user1', 'hash', 'invalid');
            $this->fail('Expected invalid role to be rejected');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContains('Invalid role "invalid"', $e->getMessage()); // ✓
        }

        // Username normalization (bonus evidence)
        $user = User::create('user-5', '  UserName  ', 'hash', 'librarian');
        $this->assertEquals('username', $user->username()); // Normalized ✓

        // All T-06 role validation evidence satisfied ✓
    }
}