<?php

namespace Library\Adapters\Outbound\Storage;

use Library\Application\Ports\Outbound\CoverStoragePort;
use Library\Application\Ports\Outbound\CoverStorageException;

/**
 * Fake Cover Storage
 * 
 * In-memory implementation for testing cover storage operations.
 */
final class FakeCoverStorage implements CoverStoragePort
{
    /** @var array<string, array> */
    private array $covers = [];
    
    private int $nextKeyCounter = 1;

    public function store(string $fileContent, string $mimeType, string $originalFilename): string
    {
        // Validate first
        $this->validate($fileContent, $mimeType);
        
        // Generate opaque key
        $key = 'cover-' . $this->nextKeyCounter++;
        
        // Store cover metadata
        $this->covers[$key] = [
            'content' => $fileContent,
            'mimeType' => $mimeType,
            'originalFilename' => $originalFilename,
            'size' => strlen($fileContent),
            'storedAt' => time()
        ];
        
        return $key;
    }

    public function delete(string $coverKey): void
    {
        if (!$this->exists($coverKey)) {
            throw new CoverStorageException("Cover with key '{$coverKey}' not found", 404);
        }
        
        unset($this->covers[$coverKey]);
    }

    public function exists(string $coverKey): bool
    {
        return isset($this->covers[$coverKey]);
    }

    public function validate(string $fileContent, string $mimeType): void
    {
        // Validate file size (max 2MB - CA-03.4)
        if (strlen($fileContent) > 2 * 1024 * 1024) {
            throw new CoverStorageException('File size exceeds 2MB limit', 422);
        }
        
        // Validate MIME type (JPG/PNG only - CA-03.4)
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
        if (!in_array($mimeType, $allowedTypes)) {
            throw new CoverStorageException(
                'Invalid file type. Only JPEG and PNG are allowed', 
                422
            );
        }
        
        // Validate content is not empty
        if (empty($fileContent)) {
            throw new CoverStorageException('File content is empty', 422);
        }
        
        // Basic image validation (check for image headers)
        $this->validateImageContent($fileContent, $mimeType);
    }

    /**
     * Test helper: clear all covers
     */
    public function clear(): void
    {
        $this->covers = [];
        $this->nextKeyCounter = 1;
    }

    /**
     * Test helper: get cover data
     */
    public function getCoverData(string $coverKey): ?array
    {
        return $this->covers[$coverKey] ?? null;
    }

    /**
     * Test helper: get all covers
     */
    public function getAllCovers(): array
    {
        return $this->covers;
    }

    /**
     * Test helper: create fake image content
     */
    public static function createFakeImageContent(string $type = 'png'): string
    {
        // Minimal valid image headers for testing
        return match ($type) {
            'png' => "\x89PNG\r\n\x1a\n" . str_repeat('x', 100), // PNG signature + data
            'jpg', 'jpeg' => "\xFF\xD8\xFF" . str_repeat('x', 100), // JPEG signature + data
            default => throw new \InvalidArgumentException("Unsupported type: {$type}")
        };
    }

    /**
     * Validate basic image content structure
     */
    private function validateImageContent(string $content, string $mimeType): void
    {
        // Check for basic image file signatures
        $isValidImage = match ($mimeType) {
            'image/png' => str_starts_with($content, "\x89PNG\r\n\x1a\n"),
            'image/jpeg', 'image/jpg' => str_starts_with($content, "\xFF\xD8\xFF"),
            default => false
        };
        
        if (!$isValidImage) {
            throw new CoverStorageException(
                'File content does not match the specified image type', 
                422
            );
        }
    }
}