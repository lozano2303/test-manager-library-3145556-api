<?php

namespace Library\Domain\Services;

use DateTimeImmutable;
use DateTimeZone;

/**
 * System Clock Implementation
 * 
 * Production implementation that uses actual system time.
 */
final readonly class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}