<?php

declare(strict_types=1);

namespace Anil\FileExport\Engine\Contracts;

use Anil\FileExport\DataTransferObjects\CsvSettings;
use Anil\FileExport\DataTransferObjects\StyleConfig;
use Closure;
use PhpOffice\PhpSpreadsheet\Cell\IValueBinder;

/**
 * A single renderable sheet consumed by the export engine.
 *
 * Decouples the engine from how rows are produced: a sheet exposes its
 * headings and a streaming row iterator (already mapped and serial-numbered),
 * plus the styling/formatting metadata needed to render it. Implementations
 * must keep rows() lazy so multi-million-row exports stay memory-bounded.
 */
interface ExportSheet
{
    /**
     * The worksheet tab title.
     */
    public function title(): string;

    /**
     * Column header labels for the first row.
     *
     * @return array<int, string>
     */
    public function headings(): array;

    /**
     * Stream the mapped, serial-numbered data rows.
     *
     * Must be lazy (a generator) — never materialize the full dataset.
     *
     * @return iterable<int, array<int, mixed>>
     */
    public function rows(): iterable;

    /**
     * Styling applied to this sheet.
     */
    public function styling(): StyleConfig;

    /**
     * Column number/date formats, keyed by column letter.
     *
     * @return array<string, string>
     */
    public function columnFormats(): array;

    /**
     * Explicit column widths, keyed by column letter.
     *
     * @return array<string, float|int>
     */
    public function columnWidths(): array;

    /**
     * Whether columns should be auto-sized (expensive on large sheets).
     */
    public function autoSize(): bool;

    /**
     * Whether full-range styling (wrap text, auto-filter) should be applied.
     *
     * Disabled for very large / auto-split sheets where it is prohibitively slow.
     */
    public function applyFullRangeStyling(): bool;

    /**
     * CSV/TSV writer settings (used only by delimited-text formats).
     */
    public function csvSettings(): CsvSettings;

    /**
     * The top-left cell where the sheet's data begins (e.g. "A1", "B2").
     */
    public function startCellCoordinate(): string;

    /**
     * Whether to use strict null comparison when writing cell values.
     */
    public function strictNullComparison(): bool;

    /**
     * A callback to style the rendered worksheet, or null.
     *
     * @return Closure(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet): void|null
     */
    public function styleCallback(): ?Closure;

    /**
     * A custom value binder for cell type inference, or null.
     */
    public function valueBinder(): ?IValueBinder;

    /**
     * Charts to attach to the worksheet (xlsx only).
     *
     * @return array<int, \PhpOffice\PhpSpreadsheet\Chart\Chart>
     */
    public function charts(): array;

    /**
     * Images/drawings to attach to the worksheet (xlsx only).
     *
     * @return array<int, \PhpOffice\PhpSpreadsheet\Worksheet\BaseDrawing>
     */
    public function drawings(): array;
}
