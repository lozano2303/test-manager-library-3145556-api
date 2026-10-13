<?php

namespace Tests\Unit\Application\Services;

use Library\Application\Ports\Outbound\PasswordHasherPort;

/**
 * Fake Password Hasher for testing T-07 services
 */
class FakePasswordHasher implements PasswordHasherPort
{
    public function hash(string $plainPassword): string
    {
        // Simple fake hashing - just prepend "hashed_"
        return 'hashed_' . $plainPassword;
    }

    public function verify(string $plainPassword, string $hashedPassword): bool
    {
        return $hashedPassword === 'hashed_' . $plainPassword;
    }
}