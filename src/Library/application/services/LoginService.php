<?php

namespace Library\Application\Services;

use Library\Application\Ports\Outbound\UserRepositoryPort;
use Library\Application\Ports\Outbound\PasswordHasherPort;
use Library\Application\Ports\Outbound\TokenGeneratorPort;
use InvalidArgumentException;

/**
 * Login Service
 * 
 * Handles user authentication (HU-01)
 */
class LoginService
{
    public function __construct(
        private UserRepositoryPort $userRepository,
        private PasswordHasherPort $passwordHasher,
        private TokenGeneratorPort $tokenGenerator
    ) {}

    /**
     * Authenticate user and generate token
     * 
     * @throws InvalidArgumentException When credentials are invalid (CA-01.2)
     */
    public function login(string $username, string $password): string
    {
        // Normalize username (lowercase, trimmed)
        $normalizedUsername = strtolower(trim($username));
        
        $user = $this->userRepository->findByUsername($normalizedUsername);
        
        // CA-01.2: Generic error message that doesn't reveal if username or password failed
        if (!$user || !$this->passwordHasher->verify($password, $user->getPasswordHash())) {
            throw new InvalidArgumentException('Invalid credentials');
        }
        
        // CA-01.1: Generate token valid for 8 hours
        return $this->tokenGenerator->generate($user);
    }
}