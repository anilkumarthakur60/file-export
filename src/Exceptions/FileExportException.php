<?php

declare(strict_types=1);

namespace Anil\FileExport\Exceptions;

use RuntimeException;

/**
 * Thrown when a file export operation fails.
 *
 * Use the static factory methods for consistent, descriptive error messages.
 */
final class FileExportException extends RuntimeException
{
    /**
     * No data source was configured before calling an output method.
     */
    public static function missingDataSource(): self
    {
        return new self(
            'No data source provided. Call fromQuery() or fromCollection() before exporting.'
        );
    }

    /**
     * No column headers were configured before calling an output method.
     */
    public static function missingHeaders(): self
    {
        return new self(
            'No headers provided. Call withHeaders() before exporting.'
        );
    }

    /**
     * No row mapping callback was configured before calling an output method.
     */
    public static function missingMapping(): self
    {
        return new self(
            'No mapping callback provided. Call withMapping() before exporting.'
        );
    }

    /**
     * The file could not be written to the configured storage disk.
     */
    public static function storageFailed(string $filename): self
    {
        return new self("Failed to store export file: {$filename}");
    }

    /**
     * No sheets were configured in a multi-sheet export.
     */
    public static function emptySheets(): self
    {
        return new self(
            'No sheets configured. Call addSheet() at least once before exporting.'
        );
    }

    /**
     * The export exceeds the configured maximum row count.
     */
    public static function rowLimitExceeded(int $count, int $max): self
    {
        return new self(
            "Export row count ({$count}) exceeds the configured maximum ({$max}). ".
            'Raise file-export.max_rows (or set it to null), use a format without a row limit '.
            'such as CSV, or enable auto-split for xlsx/ods exports.'
        );
    }

    /**
     * A queued export was attempted with a live (non-serializable) query builder.
     */
    public static function queueRequiresSerializableQuery(): self
    {
        return new self(
            'Queued query exports require a serializable query. A live Eloquent '.
            'builder holds a database connection that cannot be queued — pass a '.
            'factory closure instead, e.g. ->fromQuery(fn () => User::query()).'
        );
    }

    /**
     * Auto-split was requested for a format that does not support multiple sheets.
     */
    public static function autoSplitUnsupported(string $format): self
    {
        return new self(
            "Auto-split is not supported for the '{$format}' format. ".
            'Use xlsx or ods for multi-sheet splitting, or CSV for unlimited single-file exports.'
        );
    }
}
