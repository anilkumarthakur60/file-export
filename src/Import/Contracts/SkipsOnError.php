<?php

declare(strict_types=1);

namespace Anil\FileExport\Import\Contracts;

use Throwable;

/**
 * Skips rows that throw during persistence instead of aborting the import.
 */
interface SkipsOnError
{
    public function onError(Throwable $e): void;
}
