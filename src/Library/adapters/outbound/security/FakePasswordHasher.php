<?php

namespace Library\Adapters\Outbound\Security;

use Library\Application\Ports\Outbound\PasswordHasherPort;

/**
 * Fake Password Hasher
 * 
 * Simple implementation for testing that doesn't actually hash passwords.
 * Used in unit tests and fake implementations.
 */
final class FakePasswordHasher implements PasswordHasherPort
{
    /**
     * Fake hash - just prefixes with "hashed:"
     */
    public function hash(string $plainPassword): string
    {
        return 'hashed:' . $plainPassword;
    }

    /**
     * Fake verification - checks if hash starts with "hashed:" and matches
     */
    public function verify(string $plainPassword, string $hash): bool
    {
        return $hash === 'hashed:' . $plainPassword;
    }
}