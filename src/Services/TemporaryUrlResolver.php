<?php

declare(strict_types=1);

namespace Anil\FileExport\Services;

use Anil\FileExport\Contracts\UrlResolver;
use Anil\FileExport\DataTransferObjects\ExportConfig;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Resolves download URLs, preferring temporary signed URLs for cloud disks.
 *
 * Attempts to generate a temporary (signed) URL first. If the disk driver
 * does not support temporary URLs (e.g., local disk), falls back to a
 * regular public URL.
 */
final class TemporaryUrlResolver implements UrlResolver
{
    public function __construct(
        private readonly ExportConfig $config,
        private readonly FilesystemManager $filesystemManager,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function resolve(string $disk, string $path, ?int $expiresInDays = null): string
    {
        $storage = $this->filesystemManager->disk($disk);

        try {
            $expiresAt = Carbon::now()->addDays($expiresInDays ?? $this->config->expireDays);

            return $storage->temporaryUrl($path, $expiresAt);
        } catch (RuntimeException) {
            return $storage->url($path);
        }
    }
}
