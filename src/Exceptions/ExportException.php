<?php

declare(strict_types=1);

namespace Anil\FileExport\Exceptions;

use RuntimeException;

final class ExportException extends RuntimeException
{
    public static function missingDataSource(): self
    {
        return new self('No data source provided. Call fromQuery() or fromCollection() before exporting.');
    }

    public static function missingHeaders(): self
    {
        return new self('No headers provided. Call withHeaders() before exporting.');
    }

    public static function missingMapping(): self
    {
        return new self('No mapping callback provided. Call withMapping() before exporting.');
    }

    public static function storageFailed(string $filename): self
    {
        return new self("Failed to store export file: {$filename}");
    }

    public static function emptySheets(): self
    {
        return new self('No sheets configured. Call addSheet() at least once before exporting.');
    }

    public static function conflictingModes(): self
    {
        return new self('Cannot mix single-sheet (fromQuery/fromCollection) with multi-sheet (addSheet). Use one or the other.');
    }
}
