<?php

declare(strict_types=1);

namespace Anil\FileExport\Import\Contracts;

use Anil\FileExport\Import\Failure;

/**
 * Skips rows that fail validation instead of throwing.
 */
interface SkipsOnFailure
{
    /**
     * @param  array<int, Failure>  $failures
     */
    public function onFailure(array $failures): void;
}
