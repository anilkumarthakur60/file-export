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

    public static function rowLimitExceeded(int $count, int $maxRows): self
    {
        return new self("Row count ({$count}) exceeds the configured maximum ({$maxRows}). Increase max_rows in config or use withLimit().");
    }
}
