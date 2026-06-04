<?php

declare(strict_types=1);

namespace Anil\FileExport\Import;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Restricts a PhpSpreadsheet read to the heading row plus a row window, so a
 * chunked import only loads the slice it needs into memory.
 */
final class ChunkReadFilter implements IReadFilter
{
    public function __construct(
        private readonly int $startRow,
        private readonly int $endRow,
        private readonly int $headingRow = 0,
    ) {}

    public function readCell($columnAddress, $row, $worksheetName = ''): bool
    {
        if ($this->headingRow > 0 && $row === $this->headingRow) {
            return true;
        }

        return $row >= $this->startRow && $row <= $this->endRow;
    }
}
