<?php

declare(strict_types=1);

namespace Anil\FileExport\Contracts;

/**
 * Resolves a download URL for a stored export file.
 *
 * Bind your own implementation to return CDN URLs, custom signed URLs,
 * or any other URL scheme your application uses.
 */
interface UrlResolver
{
    /**
     * Resolve a publicly accessible download URL for the given file.
     *
     * @param  string  $disk  The filesystem disk name where the file is stored.
     * @param  string  $path  The file path relative to the disk root.
     * @param  int|null  $expiresInDays  Signed-URL lifetime in days (cloud disks). Null = package default.
     * @return string  The download URL.
     */
    public function resolve(string $disk, string $path, ?int $expiresInDays = null): string;
}
