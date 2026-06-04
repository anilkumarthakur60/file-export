<?php

declare(strict_types=1);

namespace Anil\FileExport\Import\Contracts;

/**
 * Reads the file in row windows of this size — dispatched as separate queued
 * jobs so very large files import with bounded memory.
 */
interface ReadsChunked
{
    public function chunkSize(): int;
}
