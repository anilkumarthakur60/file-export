<?php

declare(strict_types=1);

namespace Anil\FileExport\Import\Contracts;

/**
 * Treats a row as the heading row, keying every data row by its labels.
 */
interface UsesHeadingRow
{
    /**
     * The 1-based row number containing the headings (typically 1).
     */
    public function headingRow(): int;
}
