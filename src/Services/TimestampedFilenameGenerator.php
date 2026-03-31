<?php

declare(strict_types=1);

namespace Anil\FileExport\Services;

use Anil\FileExport\Contracts\FilenameGenerator;
use Anil\FileExport\DataTransferObjects\ExportConfig;
use Anil\FileExport\Enums\ExportFormat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Generates filenames using the pattern: {name}_{timestamp}.{ext}
 *
 * When no custom name is provided, an auto-generated name is created
 * from the configured prefix and a random suffix.
 *
 * Example output: "users_2024-01-15_143022.xlsx"
 * Example auto:   "export-a1b2c3d4_2024-01-15_143022.xlsx"
 */
final class TimestampedFilenameGenerator implements FilenameGenerator
{
    public function __construct(
        private readonly ExportConfig $config,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function generate(?string $customName, ExportFormat $format): string
    {
        $baseName = $customName ?? $this->config->filenamePrefix.'-'.Str::random($this->config->randomSuffixLength);
        $timestamp = Carbon::now()->format($this->config->timestampFormat);

        return Str::slug($baseName).'_'.$timestamp.'.'.$format->extension();
    }
}
