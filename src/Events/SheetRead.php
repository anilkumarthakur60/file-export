<?php

declare(strict_types=1);

namespace Anil\FileExport\Events;

use Illuminate\Foundation\Events\Dispatchable;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Fired after a worksheet has been read by an importable.
 */
final class SheetRead
{
    use Dispatchable;

    public function __construct(
        public readonly object $importable,
        public readonly Worksheet $worksheet,
    ) {}
}
