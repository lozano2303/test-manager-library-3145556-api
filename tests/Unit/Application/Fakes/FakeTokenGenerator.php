<?php

namespace Tests\Unit\Application\Fakes;

use Library\Application\Ports\Outbound\TokenGeneratorPort;
use Library\Domain\Entities\User;

/**
 * Fake Token Generator for Testing
 * 
 * Generates predictable tokens for testing
 */
class FakeTokenGenerator implements TokenGeneratorPort
{
    private array $validTokens = [];

    public function generateToken(User $user): string
    {
        $token = 'token_' . $user->getId() . '_' . $user->getUsername();
        $this->validTokens[$token] = $user->getId();
        return $token;
    }

    public function validateToken(string $token): ?string
    {
        return $this->validTokens[$token] ?? null;
    }

    // Test helper methods
    public function reset(): void
    {
        $this->validTokens = [];
    }

    public function invalidateToken(string $token): void
    {
        unset($this->validTokens[$token]);
    }

    public function getValidTokens(): array
    {
        return $this->validTokens;
    }
}