<?php

namespace Tests\Unit\Application\Services;

use PHPUnit\Framework\TestCase;
use Library\Application\Services\LoginService;
use Library\Application\Services\InvalidCredentialsException;
use Library\Domain\Entities\User;
use Tests\Unit\Application\Fakes\FakeUserRepository;
use Tests\Unit\Application\Fakes\FakePasswordHasher;
use Tests\Unit\Application\Fakes\FakeTokenGenerator;

/**
 * Login Service Test
 * 
 * Tests HU-01: User authentication
 */
class LoginServiceTest extends TestCase
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
    }

    /** @test HU-01.1: Valid credentials return token */
    public function valid_credentials_return_token(): void
    {
        // Arrange
        $user = User::create('1', 'john', 'hashed_password123', 'librarian');
        $this->userRepository->addUser($user);

        // Act
        $token = $this->loginService->login('john', 'password123');

        // Assert
        $this->assertNotEmpty($token);
        $this->assertEquals('1', $this->tokenGenerator->validateToken($token));
    }

    /** @test HU-01.2: Invalid username throws exception */
    public function invalid_username_throws_exception(): void
    {
        // Arrange
        $user = User::create('1', 'john', 'hashed_password123', 'librarian');
        $this->userRepository->addUser($user);

        // Assert & Act
        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Invalid credentials');
        
        $this->loginService->login('nonexistent', 'password123');
    }

    /** @test HU-01.2: Invalid password throws exception */
    public function invalid_password_throws_exception(): void
    {
        // Arrange
        $user = User::create('1', 'john', 'hashed_password123', 'librarian');
        $this->userRepository->addUser($user);

        // Assert & Act
        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Invalid credentials');
        
        $this->loginService->login('john', 'wrongpassword');
    }

    /** @test Username normalization during login */
    public function username_is_normalized_during_login(): void
    {
        // Arrange
        $user = User::create('1', 'john', 'hashed_password123', 'librarian');
        $this->userRepository->addUser($user);

        // Act - whitespace and case variations
        $token1 = $this->loginService->login('  JOHN  ', 'password123');
        $token2 = $this->loginService->login('John', 'password123');

        // Assert
        $this->assertNotEmpty($token1);
        $this->assertNotEmpty($token2);
        $this->assertEquals('1', $this->tokenGenerator->validateToken($token1));
        $this->assertEquals('1', $this->tokenGenerator->validateToken($token2));
    }

    /** @test Admin user can login */
    public function admin_user_can_login(): void
    {
        // Arrange
        $adminUser = User::create('1', 'admin', 'hashed_adminpass', 'admin');
        $this->userRepository->addUser($adminUser);

        // Act
        $token = $this->loginService->login('admin', 'adminpass');

        // Assert
        $this->assertNotEmpty($token);
        $this->assertEquals('1', $this->tokenGenerator->validateToken($token));
    }
}