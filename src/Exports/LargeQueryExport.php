<?php

declare(strict_types=1);

namespace Anil\FileExport\Exports;

/**
 * Query export tuned for very large / auto-split sheets.
 *
 * Identical to QueryExport but does NOT auto-size columns: measuring every
 * cell to compute column widths is far too slow and memory-hungry on sheets
 * approaching the 1,048,576-row limit. Use explicit column widths
 * (withColumnWidths) when precise widths are needed.
 */
class LargeQueryExport extends BaseQueryExport
{
    public function autoSize(): bool
    {
        return false;
    }
}
