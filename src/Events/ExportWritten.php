<?php

declare(strict_types=1);

namespace Anil\FileExport\Events;

use Anil\FileExport\Engine\ExportDocument;
use Illuminate\Foundation\Events\Dispatchable;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Fired after all sheets are written, just before the workbook is saved.
 */
final class ExportWritten
{
    use Dispatchable;

    public function __construct(
        public readonly Spreadsheet $spreadsheet,
        public readonly ExportDocument $document,
    ) {}
}
