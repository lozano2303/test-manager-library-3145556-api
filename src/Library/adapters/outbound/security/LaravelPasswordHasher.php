<?php

namespace Library\Adapters\Outbound\Security;

use Library\Application\Ports\Outbound\PasswordHasherPort;
use Illuminate\Support\Facades\Hash;

class LaravelPasswordHasher implements PasswordHasherPort
{
    public function hash(string $plainPassword): string
    {
        return Hash::make($plainPassword);
    }

    public function verify(string $plainPassword, string $hash): bool
    {
        return Hash::check($plainPassword, $hash);
    }
}