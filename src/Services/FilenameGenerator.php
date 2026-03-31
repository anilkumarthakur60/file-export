<?php

declare(strict_types=1);

namespace Anil\FileExport\Services;

use Anil\FileExport\Contracts\FilenameGeneratorContract;
use Anil\FileExport\DataTransferObjects\ExportConfig;
use Anil\FileExport\Enums\ExportFormat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class FilenameGenerator implements FilenameGeneratorContract
{
    public function __construct(
        private readonly ExportConfig $config,
    ) {}

    public function generate(?string $customName, ExportFormat $format): string
    {
        $name = $customName ?? 'export-'.Str::random(8);
        $timestamp = Carbon::now()->format($this->config->timestampFormat);

        return Str::slug($name).'_'.$timestamp.'.'.$format->extension();
    }
}
