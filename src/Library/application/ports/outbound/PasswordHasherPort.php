<?php

namespace Library\Application\Ports\Outbound;

/**
 * Password Hasher Port
 * 
 * Abstracts password hashing and verification operations.
 * Implementation will handle secure hashing (bcrypt, argon2, etc).
 */
interface PasswordHasherPort
{
    /**
     * Hash a plain text password
     */
    public function hash(string $plainPassword): string;

    /**
     * Verify a plain password against a hash
     */
    public function verify(string $plainPassword, string $hash): bool;
}