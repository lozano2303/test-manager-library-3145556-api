<?php

namespace Tests\Unit\Application\Fakes;

use Library\Application\Ports\Outbound\PasswordHasherPort;

/**
 * Fake Password Hasher for Testing
 * 
 * Uses simple prefix for predictable testing
 */
class FakePasswordHasher implements PasswordHasherPort
{
    private const HASH_PREFIX = 'hashed_';

    public function hash(string $plainPassword): string
    {
        return self::HASH_PREFIX . $plainPassword;
    }

    public function verify(string $plainPassword, string $hash): bool
    {
        return $hash === self::HASH_PREFIX . $plainPassword;
    }
}