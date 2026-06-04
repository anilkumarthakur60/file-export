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
     * @param  bool  $autoSplit  Whether to auto-split oversized query exports across sheets.
     * @param  int  $rowsPerSheet  Maximum rows per sheet before auto-splitting (xlsx/ods).
     * @param  bool  $disableAutoSizeOnLargeSheets  Disable expensive auto-column-sizing on split/large sheets.
     * @param  bool  $disableStylingOnLargeSheets  Disable full-range wrap/auto-filter on split/large sheets.
     * @param  int  $mailAttachThreshold  Max file size (bytes) to attach to an email; larger files send a link.
     * @param  string  $mailSubject  Default subject for export-ready emails.
     * @param  string|null  $queueConnection  Queue connection for queued exports (null = default).
     * @param  string|null  $queueName  Queue name for queued exports (null = default).
     * @param  CsvSettings  $csv  Default CSV/TSV writer settings.
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
        public bool $autoSplit = true,
        public int $rowsPerSheet = 1000000,
        public bool $disableAutoSizeOnLargeSheets = true,
        public bool $disableStylingOnLargeSheets = true,
        public int $mailAttachThreshold = 10485760,
        public string $mailSubject = 'Your export is ready',
        public ?string $queueConnection = null,
        public ?string $queueName = null,
        public CsvSettings $csv = new CsvSettings,
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
        $autoSplit = is_array($values['auto_split'] ?? null) ? $values['auto_split'] : [];
        $mail = is_array($values['mail'] ?? null) ? $values['mail'] : [];
        $queue = is_array($values['queue'] ?? null) ? $values['queue'] : [];
        $csv = is_array($values['csv'] ?? null) ? $values['csv'] : [];

        // max_rows may be explicitly null to mean "unlimited".
        $maxRows = 1000000;
        if (array_key_exists('max_rows', $values)) {
            $maxRows = is_int($values['max_rows']) ? $values['max_rows'] : null;
        }

        return new self(
            disk: is_string($values['disk'] ?? null) ? $values['disk'] : 'public',
            path: is_string($values['path'] ?? null) ? $values['path'] : 'exports',
            expireDays: is_int($values['expire_days'] ?? null) ? $values['expire_days'] : 7,
            defaultFormat: ExportFormat::tryFrom(is_string($values['default_format'] ?? null) ? $values['default_format'] : 'xlsx') ?? ExportFormat::XLSX,
            maxRows: $maxRows,
            chunkSize: is_int($values['chunk_size'] ?? null) ? $values['chunk_size'] : 1000,
            filenamePrefix: is_string($filename['prefix'] ?? null) ? $filename['prefix'] : 'export',
            timestampFormat: is_string($filename['timestamp_format'] ?? null) ? $filename['timestamp_format'] : 'Y-m-d_His',
            randomSuffixLength: is_int($filename['random_length'] ?? null) ? $filename['random_length'] : 8,
            defaultCreator: is_string($properties['creator'] ?? null) ? $properties['creator'] : '',
            defaultCompany: is_string($properties['company'] ?? null) ? $properties['company'] : '',
            styling: StyleConfig::fromArray(array_filter($styling, 'is_string', ARRAY_FILTER_USE_KEY)),
            autoSplit: is_bool($autoSplit['enabled'] ?? null) ? $autoSplit['enabled'] : true,
            rowsPerSheet: is_int($autoSplit['rows_per_sheet'] ?? null) ? $autoSplit['rows_per_sheet'] : 1000000,
            disableAutoSizeOnLargeSheets: is_bool($autoSplit['disable_autosize'] ?? null) ? $autoSplit['disable_autosize'] : true,
            disableStylingOnLargeSheets: is_bool($autoSplit['disable_styling_on_large_sheets'] ?? null) ? $autoSplit['disable_styling_on_large_sheets'] : true,
            mailAttachThreshold: is_int($mail['attach_threshold'] ?? null) ? $mail['attach_threshold'] : 10485760,
            mailSubject: is_string($mail['subject'] ?? null) ? $mail['subject'] : 'Your export is ready',
            queueConnection: is_string($queue['connection'] ?? null) ? $queue['connection'] : null,
            queueName: is_string($queue['queue'] ?? null) ? $queue['queue'] : null,
            csv: CsvSettings::fromArray(array_filter($csv, 'is_string', ARRAY_FILTER_USE_KEY)),
        );
    }
}
