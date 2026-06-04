<?php

declare(strict_types=1);

namespace Anil\FileExport\Import\Contracts;

/**
 * Stops reading cells at a given column letter (e.g. "Z").
 */
interface LimitsColumns
{
    public function endColumn(): string;
}
