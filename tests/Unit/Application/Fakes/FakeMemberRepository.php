<?php

namespace Tests\Unit\Application\Fakes;

use Library\Application\Ports\Outbound\MemberRepositoryPort;
use Library\Domain\Entities\Member;

class FakeMemberRepository implements MemberRepositoryPort
{
    private array $members = [];
    private int $nextId = 1;

    public function save(Member $member): void
    {
        $this->members[$member->getId()] = $member;
    }

    public function findById(string $id): ?Member
    {
        return $this->members[$id] ?? null;
    }

    public function nextId(): string
    {
        return (string) $this->nextId++;
    }

    public function addMember(Member $member): void
    {
        $this->members[$member->getId()] = $member;
    }

    public function reset(): void
    {
        $this->members = [];
        $this->nextId = 1;
    }
}