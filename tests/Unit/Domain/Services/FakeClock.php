<?php

namespace Tests\Unit\Domain\Services;

use Library\Domain\Services\Clock;
use DateTimeImmutable;

/**
 * Fake Clock for Testing
 * 
 * Allows tests to control time for predictable testing of time-dependent behavior.
 * Essential for T-05 evidence: testing loan states with controlled time.
 */
final class FakeClock implements Clock
{
    private DateTimeImmutable $fixedTime;

    public function __construct(DateTimeImmutable $fixedTime)
    {
        $this->fixedTime = $fixedTime;
    }

    /**
     * Create fake clock set to a specific time
     */
    public static function at(string $time): self
    {
        return new self(new DateTimeImmutable($time . ' UTC'));
    }

    /**
     * Create fake clock set to current time (for baseline)
     */
    public static function now(): self
    {
        return new self(new DateTimeImmutable('now', new \DateTimeZone('UTC')));
    }

    public function now(): DateTimeImmutable
    {
        return $this->fixedTime;
    }

    /**
     * Advance time by specified period
     */
    public function advance(string $period): self
    {
        return new self($this->fixedTime->modify($period));
    }

    /**
     * Set time to specific moment
     */
    public function setTo(string $time): self
    {
        return new self(new DateTimeImmutable($time . ' UTC'));
    }
}