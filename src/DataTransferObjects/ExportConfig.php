<?php

declare(strict_types=1);

namespace Anil\FileExport\DataTransferObjects;

use Anil\FileExport\Enums\ExportFormat;

/**
 * Immutable value object holding the full package configuration.
 *
 * Resolved once from the config file and registered as a singleton
 * in the service container. All services receive this via constructor
 * injection — no scattered config() calls.
 */
final readonly class ExportConfig
{
    /**
     * @param  string  $disk  Filesystem disk name for stored exports.
     * @param  string  $path  Directory path (relative to disk root) for export files.
     * @param  int  $expireDays  Temporary URL expiration in days (cloud disks only).
     * @param  ExportFormat  $defaultFormat  Default export format when none is specified.
     * @param  int|null  $maxRows  Maximum rows per export (null = unlimited).
     * @param  int  $chunkSize  Default chunk size for query-based exports.
     * @param  string  $filenamePrefix  Prefix for auto-generated filenames.
     * @param  string  $timestampFormat  PHP date format string appended to filenames.
     * @param  int  $randomSuffixLength  Length of the random string in auto-generated filenames.
     * @param  string  $defaultCreator  Default document "creator" metadata.
     * @param  string  $defaultCompany  Default document "company" metadata.
     * @param  StyleConfig  $styling  Default styling applied to all exports.
     */
    public function __construct(
        public string $disk = 'public',
        public string $path = 'exports',
        public int $expireDays = 7,
        public ExportFormat $defaultFormat = ExportFormat::XLSX,
        public ?int $maxRows = 1000000,
        public int $chunkSize = 1000,
        public string $filenamePrefix = 'export',
        public string $timestampFormat = 'Y-m-d_His',
        public int $randomSuffixLength = 8,
        public string $defaultCreator = '',
        public string $defaultCompany = '',
        public StyleConfig $styling = new StyleConfig,
    ) {}

    /**
     * Create from a config array (typically from config/file-export.php).
     *
     * @param  array<string, mixed>  $values
     */
    public static function fromArray(array $values): self
    {
        $filename = is_array($values['filename'] ?? null) ? $values['filename'] : [];
        $properties = is_array($values['properties'] ?? null) ? $values['properties'] : [];
        $styling = is_array($values['styling'] ?? null) ? $values['styling'] : [];

        return new self(
            disk: is_string($values['disk'] ?? null) ? $values['disk'] : 'public',
            path: is_string($values['path'] ?? null) ? $values['path'] : 'exports',
            expireDays: is_int($values['expire_days'] ?? null) ? $values['expire_days'] : 7,
            defaultFormat: ExportFormat::tryFrom(is_string($values['default_format'] ?? null) ? $values['default_format'] : 'xlsx') ?? ExportFormat::XLSX,
            maxRows: is_int($values['max_rows'] ?? null) ? $values['max_rows'] : 1000000,
            chunkSize: is_int($values['chunk_size'] ?? null) ? $values['chunk_size'] : 1000,
            filenamePrefix: is_string($filename['prefix'] ?? null) ? $filename['prefix'] : 'export',
            timestampFormat: is_string($filename['timestamp_format'] ?? null) ? $filename['timestamp_format'] : 'Y-m-d_His',
            randomSuffixLength: is_int($filename['random_length'] ?? null) ? $filename['random_length'] : 8,
            defaultCreator: is_string($properties['creator'] ?? null) ? $properties['creator'] : '',
            defaultCompany: is_string($properties['company'] ?? null) ? $properties['company'] : '',
            styling: StyleConfig::fromArray(array_filter($styling, 'is_string', ARRAY_FILTER_USE_KEY)),
        );
    }
}
