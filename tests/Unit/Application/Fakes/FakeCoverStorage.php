<?php

namespace Tests\Unit\Application\Fakes;

use Library\Application\Ports\Outbound\CoverStoragePort;
use Library\Application\Services\FileTooLargeException;
use Library\Application\Services\InvalidFileTypeException;

/**
 * Fake Cover Storage for Testing
 */
class FakeCoverStorage implements CoverStoragePort
{
    private array $covers = [];
    private int $nextKey = 1;
    private const MAX_SIZE = 2 * 1024 * 1024; // 2MB
    private const ALLOWED_TYPES = ['image/jpeg', 'image/png'];

    public function store($fileContent, string $mimeType, int $size): string
    {
        // Validate file size
        if ($size > self::MAX_SIZE) {
            throw new FileTooLargeException('File size exceeds 2MB limit');
        }

        // Validate MIME type
        if (!in_array($mimeType, self::ALLOWED_TYPES)) {
            throw new InvalidFileTypeException('Only JPG and PNG files are allowed');
        }

        $key = 'cover_' . $this->nextKey++;
        $this->covers[$key] = [
            'content' => $fileContent,
            'mimeType' => $mimeType,
            'size' => $size
        ];
        
        return $key;
    }

    public function delete(string $key): void
    {
        unset($this->covers[$key]);
    }

    public function exists(string $key): bool
    {
        return isset($this->covers[$key]);
    }

    public function getUrl(string $key): string
    {
        if (!$this->exists($key)) {
            throw new InvalidArgumentException('Cover not found');
        }
        return '/covers/' . $key;
    }

    // Test helper methods
    public function reset(): void
    {
        $this->covers = [];
        $this->nextKey = 1;
    }

    public function getCoverCount(): int
    {
        return count($this->covers);
    }

    public function getCover(string $key): ?array
    {
        return $this->covers[$key] ?? null;
    }
}