<?php

namespace Library\Application\Ports\Outbound;

use Library\Domain\Entities\Member;

interface MemberRepositoryPort
{
    public function save(Member $member): void;
    public function findById(string $id): ?Member;
    public function nextId(): string;
}