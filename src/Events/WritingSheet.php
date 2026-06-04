<?php

declare(strict_types=1);

namespace Anil\FileExport\Events;

use Anil\FileExport\Engine\Contracts\ExportSheet;
use Illuminate\Foundation\Events\Dispatchable;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Fired before a sheet's data is written.
 */
final class WritingSheet
{
    use Dispatchable;

    public function __construct(
        public readonly Worksheet $worksheet,
        public readonly ExportSheet $sheet,
    ) {}
}
