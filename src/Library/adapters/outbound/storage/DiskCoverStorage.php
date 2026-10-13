<?php

namespace Library\Adapters\Outbound\Storage;

use Library\Application\Ports\Outbound\CoverStoragePort;
use Library\Application\Services\FileTooLargeException;
use Library\Application\Services\InvalidFileTypeException;

class DiskCoverStorage implements CoverStoragePort
{
    private const MAX_SIZE = 2 * 1024 * 1024; // 2MB
    private const ALLOWED_TYPES = ['image/jpeg', 'image/png'];
    private string $storagePath;

    public function __construct(string $storagePath = '/storage/covers')
    {
        $this->storagePath = $storagePath;
    }

    public function store($fileContent, string $mimeType, int $size): string
    {
        // Validate size (CA-03.4: max 2MB)
        if ($size > self::MAX_SIZE) {
            throw new FileTooLargeException('File size exceeds 2MB limit');
        }

        // Validate MIME type (JPG/PNG only)
        if (!in_array($mimeType, self::ALLOWED_TYPES)) {
            throw new InvalidFileTypeException('Only JPG and PNG files are allowed');
        }

        // Generate opaque key
        $key = uniqid('cover_', true);
        $extension = $mimeType === 'image/jpeg' ? 'jpg' : 'png';
        $filename = $key . '.' . $extension;

        // Store file (simplified - in real implementation would use Laravel Storage)
        $filepath = $this->storagePath . '/' . $filename;
        
        // CA-03.4: Return opaque key, never expose internal path
        return $key;
    }

    public function delete(string $key): void
    {
        // Implementation would delete file from disk
    }

    public function exists(string $key): bool
    {
        // Implementation would check if file exists
        return true;
    }

    public function getUrl(string $key): string
    {
        return '/covers/' . $key;
    }
}