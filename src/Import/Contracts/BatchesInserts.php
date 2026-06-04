<?php

declare(strict_types=1);

namespace Anil\FileExport\Import\Contracts;

/**
 * Buffers mapped models and persists them in bulk inserts of this size.
 */
interface BatchesInserts
{
    public function batchSize(): int;
}
