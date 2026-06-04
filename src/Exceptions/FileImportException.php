<?php

declare(strict_types=1);

namespace Anil\FileExport\Exceptions;

use RuntimeException;

/**
 * Thrown when a file import operation fails.
 */
final class FileImportException extends RuntimeException
{
    /**
     * No source file was provided before reading.
     */
    public static function missingFile(): self
    {
        return new self('No import file provided. Call from() with a file path before reading.');
    }

    /**
     * The source file could not be read from the configured disk.
     */
    public static function unreadableFile(string $path): self
    {
        return new self("Unable to read import file: {$path}");
    }
}
