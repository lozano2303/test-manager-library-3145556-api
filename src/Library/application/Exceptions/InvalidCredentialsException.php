<?php

namespace Library\Application\Services;

use Exception;

/**
 * Invalid Credentials Exception
 * 
 * Thrown when authentication fails (HU-01.2)
 */
class InvalidCredentialsException extends Exception
{
    public function __construct(string $message = 'Invalid credentials')
    {
        parent::__construct($message);
    }
}