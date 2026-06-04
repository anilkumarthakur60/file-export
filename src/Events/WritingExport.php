<?php

declare(strict_types=1);

namespace Anil\FileExport\Events;

use Anil\FileExport\Engine\ExportDocument;
use Illuminate\Foundation\Events\Dispatchable;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Fired before any sheet is written, after the workbook is created.
 */
final class WritingExport
{
    use Dispatchable;

    public function __construct(
        public readonly Spreadsheet $spreadsheet,
        public readonly ExportDocument $document,
    ) {}
}
