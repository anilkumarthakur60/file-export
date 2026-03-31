<?php

declare(strict_types=1);

namespace Anil\FileExport\DataTransferObjects;

use Anil\FileExport\Enums\ExportFormat;

final readonly class ExportConfig
{
    public function __construct(
        public string $disk = 'public',
        public string $path = 'exports',
        public int $expireDays = 7,
        public ExportFormat $defaultFormat = ExportFormat::XLSX,
        public ?int $maxRows = 1000000,
        public int $chunkSize = 1000,
        public string $timestampFormat = 'Y-m-d_His',
        public StyleConfig $styling = new StyleConfig,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $disk = $config['disk'] ?? 'public';
        $path = $config['path'] ?? 'exports';
        $expireDays = $config['expire_days'] ?? 7;
        $defaultFormat = $config['default_format'] ?? 'xlsx';
        $maxRows = $config['max_rows'] ?? 1000000;
        $chunkSize = $config['chunk_size'] ?? 1000;
        $timestampFormat = $config['timestamp_format'] ?? 'Y-m-d_His';
        $styling = $config['styling'] ?? [];

        return new self(
            disk: is_string($disk) ? $disk : 'public',
            path: is_string($path) ? $path : 'exports',
            expireDays: is_int($expireDays) ? $expireDays : 7,
            defaultFormat: ExportFormat::tryFrom(is_string($defaultFormat) ? $defaultFormat : 'xlsx') ?? ExportFormat::XLSX,
            maxRows: is_int($maxRows) ? $maxRows : 1000000,
            chunkSize: is_int($chunkSize) ? $chunkSize : 1000,
            timestampFormat: is_string($timestampFormat) ? $timestampFormat : 'Y-m-d_His',
            styling: StyleConfig::fromArray(is_array($styling) ? array_filter($styling, 'is_string', ARRAY_FILTER_USE_KEY) : []),
        );
    }
}
