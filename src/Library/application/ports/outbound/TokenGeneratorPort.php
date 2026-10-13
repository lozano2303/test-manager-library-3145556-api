<?php

namespace Library\Application\Ports\Outbound;

use Library\Domain\Entities\User;

/**
 * Token Generator Port
 * 
 * Defines the contract for JWT token generation operations.
 * Follows hexagonal architecture - application layer depends on this abstraction.
 */
interface TokenGeneratorPort
{
    /**
     * Generate JWT token for authenticated user
     * Token should be valid for 8 hours (CA-01.1)
     */
    public function generate(User $user): string;

    /**
     * Validate and decode JWT token
     * Returns user ID if valid, null if invalid/expired
     */
    public function validate(string $token): ?string;
}