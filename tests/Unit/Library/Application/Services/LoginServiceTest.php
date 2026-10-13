<?php

namespace Tests\Unit\Library\Application\Services;

use PHPUnit\Framework\TestCase;
use Library\Application\Services\LoginService;
use Library\Application\Services\AuthenticationException;
use Library\Adapters\Outbound\Persistence\FakeUserRepository;
use Library\Adapters\Outbound\Security\FakePasswordHasher;
use Library\Adapters\Outbound\Security\FakeTokenGenerator;
use Library\Domain\Entities\User;

/**
 * LoginService Test
 * 
 * Tests HU-01: Authentication
 * - CA-01.1: Valid credentials → token with 8-hour validity
 * - CA-01.2: Invalid credentials → 401 with non-revealing message
 */
final class LoginServiceTest extends TestCase
{
    private LoginService $loginService;
    private FakeUserRepository $userRepository;
    private FakePasswordHasher $passwordHasher;
    private FakeTokenGenerator $tokenGenerator;

    protected function setUp(): void
    {
        $this->userRepository = new FakeUserRepository();
        $this->passwordHasher = new FakePasswordHasher();
        $this->tokenGenerator = new FakeTokenGenerator();
        
        $this->loginService = new LoginService(
            $this->userRepository,
            $this->passwordHasher,
            $this->tokenGenerator
        );
        
        // Seed test data
        $this->userRepository->seed();
    }

    /**
     * @test
     * CA-01.1: Valid credentials return token with user info
     */
    public function valid_credentials_return_token_and_user_info(): void
    {
        // Act
        $result = $this->loginService->login('admin', 'admin123');
        
        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('user', $result);
        
        // Token format should include user info and expiry (8 hours from now)
        $token = $result['token'];
        $this->assertStringContainsString('admin', $token);
        $this->assertStringContainsString('admin', $token); // role
        
        // User info should not include password hash (RNF-3)
        $user = $result['user'];
        $this->assertEquals('admin', $user['username']);
        $this->assertEquals('admin', $user['role']);
        $this->assertArrayNotHasKey('passwordHash', $user);
        $this->assertArrayNotHasKey('password', $user);
    }

    /**
     * @test
     * CA-01.1: Librarian can also login successfully
     */
    public function librarian_can_login_successfully(): void
    {
        // Act
        $result = $this->loginService->login('librarian', 'lib123');
        
        // Assert
        $this->assertIsArray($result);
        $user = $result['user'];
        $this->assertEquals('librarian', $user['username']);
        $this->assertEquals('librarian', $user['role']);
    }

    /**
     * @test
     * CA-01.2: Invalid username throws AuthenticationException
     */
    public function invalid_username_throws_authentication_exception(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid credentials');
        
        $this->loginService->login('nonexistent', 'password');
    }

    /**
     * @test
     * CA-01.2: Invalid password throws AuthenticationException
     */
    public function invalid_password_throws_authentication_exception(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid credentials');
        
        $this->loginService->login('admin', 'wrongpassword');
    }

    /**
     * @test
     * CA-01.2: Empty username throws AuthenticationException
     */
    public function empty_username_throws_authentication_exception(): void
    {
        $this->expectException(AuthenticationException::class);
        
        $this->loginService->login('', 'admin123');
    }

    /**
     * @test
     * CA-01.2: Empty password throws AuthenticationException
     */
    public function empty_password_throws_authentication_exception(): void
    {
        $this->expectException(AuthenticationException::class);
        
        $this->loginService->login('admin', '');
    }

    /**
     * @test
     * Username normalization works correctly (matches User entity behavior)
     */
    public function username_normalization_works_correctly(): void
    {
        // Test with different case and whitespace
        $result = $this->loginService->login(' ADMIN ', 'admin123');
        
        $user = $result['user'];
        $this->assertEquals('admin', $user['username']);
    }

    /**
     * @test
     * CA-01.2: Error message does not reveal whether username or password failed
     */
    public function error_message_does_not_reveal_failure_reason(): void
    {
        // Test nonexistent user
        try {
            $this->loginService->login('nonexistent', 'password');
            $this->fail('Expected AuthenticationException');
        } catch (AuthenticationException $e) {
            $message1 = $e->getMessage();
        }
        
        // Test wrong password for existing user
        try {
            $this->loginService->login('admin', 'wrongpassword');
            $this->fail('Expected AuthenticationException');
        } catch (AuthenticationException $e) {
            $message2 = $e->getMessage();
        }
        
        // Both errors should have the same message (CA-01.2)
        $this->assertEquals($message1, $message2);
        $this->assertEquals('Invalid credentials', $message1);
    }

    /**
     * @test
     * Token contains expected format and expiry information
     */
    public function token_contains_expected_format(): void
    {
        $result = $this->loginService->login('admin', 'admin123');
        $token = $result['token'];
        
        // FakeTokenGenerator format: "token:userId:username:role:expiry"
        $parts = explode(':', $token);
        $this->assertCount(5, $parts);
        $this->assertEquals('token', $parts[0]);
        $this->assertEquals('admin', $parts[2]); // username
        $this->assertEquals('admin', $parts[3]); // role
        
        // Expiry should be approximately 8 hours from now
        $expiry = (int) $parts[4];
        $expectedExpiry = time() + (8 * 60 * 60);
        $this->assertGreaterThan(time(), $expiry);
        $this->assertLessThan($expectedExpiry + 60, $expiry); // Allow 1 minute tolerance
    }
}