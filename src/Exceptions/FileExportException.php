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
}
