<?php

namespace Library\Domain\Services;

use DateTimeImmutable;

/**
 * Clock Service Interface
 * 
 * Abstracts time operations to enable testing with fake clocks.
 * This follows the dependency inversion principle - domain depends on abstraction, not concrete time.
 */
interface Clock
{
    /**
     * Get the current time
     */
    public function now(): DateTimeImmutable;
}