<?php

declare(strict_types=1);

namespace Anil\FileExport\Import\Contracts;

/**
 * Reads at most a fixed number of data rows.
 */
interface LimitsRows
{
    public function limit(): int;
}
