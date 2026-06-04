<?php

declare(strict_types=1);

namespace Anil\FileExport\Import\Contracts;

/**
 * Starts reading data at a specific row (1-based).
 */
interface StartsAtRow
{
    public function startRow(): int;
}
