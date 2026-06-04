<?php

declare(strict_types=1);

namespace Anil\FileExport\Engine;

use Anil\FileExport\Engine\Contracts\ExportSheet;
use Anil\FileExport\Exceptions\FileExportException;
use Closure;

/**
 * A complete export: one or more sheets plus document-level metadata.
 *
 * This is the single input the export engine renders. Single-sheet exports
 * are simply a one-element document.
 */
final class ExportDocument
{
    /**
     * @param  array<int, ExportSheet>  $sheets  The sheets to render, in order.
     * @param  array<string, string>  $properties  Document metadata (title, creator, company, …).
     * @param  (Closure(\PhpOffice\PhpSpreadsheet\Style\Style): void)|null  $defaultStyleCallback  Applied to the workbook default style.
     * @param  string|array<string, mixed>|null  $background  Default background colour (RGB hex) or style array.
     * @param  bool  $precalculateFormulas  Pre-calculate formulas before saving.
     */
    public function __construct(
        public readonly array $sheets,
        public readonly array $properties = [],
        public readonly ?Closure $defaultStyleCallback = null,
        public readonly string|array|null $background = null,
        public readonly bool $precalculateFormulas = false,
    ) {}

    /**
     * The first sheet (used by single-sheet formats such as CSV/TSV).
     *
     * @throws FileExportException
     */
    public function firstSheet(): ExportSheet
    {
        $key = array_key_first($this->sheets);

        if ($key === null) {
            throw FileExportException::emptySheets();
        }

        return $this->sheets[$key];
    }
}
