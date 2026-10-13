<?php

namespace Library\Application\Ports\Outbound;

/**
 * Cover Storage Port
 * 
 * Abstracts book cover file storage operations.
 */
interface CoverStoragePort
{
    /**
     * Store cover file and return opaque key
     * 
     * @param resource|string $fileContent File content
     * @param string $mimeType MIME type (image/jpeg, image/png)
     * @param int $size File size in bytes
     * @throws FileTooLargeException When file exceeds 2MB
     * @throws InvalidFileTypeException When file type is not JPG/PNG
     * @return string Opaque storage key
     */
    public function store($fileContent, string $mimeType, int $size): string;

    /**
     * Delete cover by key
     */
    public function delete(string $key): void;

    /**
     * Check if cover exists
     */
    public function exists(string $key): bool;

    /**
     * Get cover URL by key
     */
    public function getUrl(string $key): string;
}