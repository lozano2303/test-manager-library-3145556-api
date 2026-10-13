<?php

namespace Library\Adapters\Outbound\Security;

use Library\Application\Ports\Outbound\TokenGeneratorPort;
use Library\Domain\Entities\User;

/**
 * Fake Token Generator
 * 
 * Simple implementation for testing that generates predictable tokens.
 * Used in unit tests and fake implementations.
 */
final class FakeTokenGenerator implements TokenGeneratorPort
{
    /**
     * Generate fake token with user information encoded
     * 
     * Format: "token:userId:username:role:expiry"
     * Expiry is 8 hours from now (CA-01.1)
     */
    public function generateToken(User $user): string
    {
        $expiryTime = time() + (8 * 60 * 60); // 8 hours from now
        
        return sprintf(
            'token:%s:%s:%s:%d',
            $user->id(),
            $user->username(),
            $user->role(),
            $expiryTime
        );
    }
}