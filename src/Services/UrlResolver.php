<?php

declare(strict_types=1);

namespace Anil\FileExport\Services;

use Anil\FileExport\Contracts\UrlResolverContract;
use Anil\FileExport\DataTransferObjects\ExportConfig;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class UrlResolver implements UrlResolverContract
{
    public function __construct(
        private readonly ExportConfig $config,
    ) {}

    public function resolve(string $disk, string $path): string
    {
        $storage = Storage::disk($disk);

        try {
            $expiry = Carbon::now()->addDays($this->config->expireDays);

            return $storage->temporaryUrl($path, $expiry);
        } catch (RuntimeException) {
            return $storage->url($path);
        }
    }
}
