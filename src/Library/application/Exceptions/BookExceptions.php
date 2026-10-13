<?php

namespace Library\Application\Services;

use Exception;

/**
 * Book-related Application Exceptions
 */

class DuplicateIsbnException extends Exception
{
    public function __construct(string $message = 'ISBN already exists')
    {
        parent::__construct($message);
    }
}

class BookNotFoundException extends Exception
{
    public function __construct(string $message = 'Book not found')
    {
        parent::__construct($message);
    }
}

class BookHasLoansException extends Exception
{
    public function __construct(string $message = 'Book has open loans')
    {
        parent::__construct($message);
    }
}

class InvalidQuantityException extends Exception
{
    public function __construct(string $message = 'Invalid quantity')
    {
        parent::__construct($message);
    }
}

class InvalidPaginationException extends Exception
{
    public function __construct(string $message = 'Invalid pagination parameters')
    {
        parent::__construct($message);
    }
}

class FileTooLargeException extends Exception
{
    public function __construct(string $message = 'File too large')
    {
        parent::__construct($message);
    }
}

class InvalidFileTypeException extends Exception
{
    public function __construct(string $message = 'Invalid file type')
    {
        parent::__construct($message);
    }
}