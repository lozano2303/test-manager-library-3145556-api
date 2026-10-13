<?php

namespace Library\Application\Services;

use Exception;

/**
 * Duplicate Username Exception
 * 
 * Thrown when attempting to create user with existing username (HU-02.2)
 */
class DuplicateUsernameException extends Exception
{
    public function __construct(string $message = 'Username already exists')
    {
        parent::__construct($message);
    }
}