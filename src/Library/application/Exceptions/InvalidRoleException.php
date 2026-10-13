<?php

namespace Library\Application\Services;

use Exception;

/**
 * Invalid Role Exception
 * 
 * Thrown when attempting to create admin via API (HU-02.3)
 */
class InvalidRoleException extends Exception
{
    public function __construct(string $message = 'Invalid role')
    {
        parent::__construct($message);
    }
}