<?php

declare(strict_types=1);

namespace Anil\FileExport\Exports\Concerns;

use Anil\FileExport\DataTransferObjects\CsvSettings;
use Anil\FileExport\DataTransferObjects\StyleConfig;
use Closure;
use PhpOffice\PhpSpreadsheet\Cell\IValueBinder;

/**
 * Shared sheet metadata for export classes.
 *
 * Holds styling, column formats/widths, CSV settings, the sheet title, and
 * serial-number state, and exposes the ExportSheet getters that the engine
 * reads. The actual PhpSpreadsheet styling is applied by the engine's
 * SheetStyler — this trait carries configuration only.
 */
trait HasSheetFormatting
{
    protected StyleConfig $styleConfig;

    protected ?CsvSettings $csvSettings = null;

    protected bool $includeSerialNumber = false;

    private int $serialIndex = 0;

    private ?string $sheetTitle = null;

    /** @var array<string, string> */
    protected array $columnFormats = [];

    /** @var array<string, float|int> */
    private array $columnWidths = [];

    /**
     * When true, skip the expensive full-range styling (text wrapping and
     * auto-filter) that is impractical on very large or auto-split sheets.
     */
    private bool $disableLargeSheetStyling = false;

    protected ?Closure $styleCallback = null;

    private string $startCellCoordinate = 'A1';

    private bool $useStrictNulls = false;

    private ?IValueBinder $valueBinder = null;

    /** @var array<int, \PhpOffice\PhpSpreadsheet\Chart\Chart> */
    private array $charts = [];

    /** @var array<int, \PhpOffice\PhpSpreadsheet\Worksheet\BaseDrawing> */
    private array $drawings = [];

    // ──────────────────────────────────────────
    // Fluent configuration
    // ──────────────────────────────────────────

    /**
     * Include a serial number column as the first column.
     */
    public function withSerialNumber(bool $include = true): static
    {
        $this->includeSerialNumber = $include;

        return $this;
    }

    /**
     * Override the default styling for this export.
     */
    public function withStyling(StyleConfig $config): static
    {
        $this->styleConfig = $config;

        return $this;
    }

    /**
     * Set the CSV/TSV writer settings for this export.
     */
    public function withCsvSettings(CsvSettings $settings): static
    {
        $this->csvSettings = $settings;

        return $this;
    }

    /**
     * Start serial numbering from a given offset.
     *
     * Used by auto-split so numbering stays continuous across sheets — the
     * first row of sheet N continues where sheet N-1 left off.
     */
    public function withSerialStart(int $start): static
    {
        $this->serialIndex = $start;

        return $this;
    }

    /**
     * Skip full-range styling (text wrapping and auto-filter) for this sheet.
     */
    public function disableLargeSheetStyling(bool $disable = true): static
    {
        $this->disableLargeSheetStyling = $disable;

        return $this;
    }

    /**
     * Style the rendered worksheet via a callback receiving the PhpSpreadsheet Worksheet.
     *
     * @param  Closure(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet): void  $callback
     */
    public function styleUsing(Closure $callback): static
    {
        $this->styleCallback = $callback;

        return $this;
    }

    /**
     * Set the top-left cell where the sheet's data begins (e.g. "B2").
     */
    public function startCell(string $coordinate): static
    {
        $this->startCellCoordinate = $coordinate;

        return $this;
    }

    /**
     * Use strict null comparison when writing cell values.
     */
    public function strictNulls(bool $strict = true): static
    {
        $this->useStrictNulls = $strict;

        return $this;
    }

    /**
     * Set a custom PhpSpreadsheet value binder for cell type inference.
     */
    public function bindValuesUsing(IValueBinder $binder): static
    {
        $this->valueBinder = $binder;

        return $this;
    }

    /**
     * Attach charts to the worksheet (xlsx only).
     *
     * @param  array<int, \PhpOffice\PhpSpreadsheet\Chart\Chart>  $charts
     */
    public function withCharts(array $charts): static
    {
        $this->charts = $charts;

        return $this;
    }

    /**
     * Attach images/drawings to the worksheet (xlsx only).
     *
     * @param  array<int, \PhpOffice\PhpSpreadsheet\Worksheet\BaseDrawing>  $drawings
     */
    public function withImages(array $drawings): static
    {
        $this->drawings = $drawings;

        return $this;
    }

    /**
     * Set column number/date formats using PhpSpreadsheet format constants.
     *
     * @param  array<string, string>  $formats  Keyed by column letter.
     */
    public function withColumnFormats(array $formats): static
    {
        $this->columnFormats = $formats;

        return $this;
    }

    /**
     * Set the sheet tab title.
     */
    public function withTitle(string $title): static
    {
        $this->sheetTitle = $title;

        return $this;
    }

    /**
     * Set explicit column widths. Overrides auto-size for specified columns.
     *
     * @param  array<string, float|int>  $widths  Keyed by column letter.
     */
    public function withColumnWidths(array $widths): static
    {
        $this->columnWidths = $widths;

        return $this;
    }

    // ──────────────────────────────────────────
    // ExportSheet getters
    // ──────────────────────────────────────────

    /**
     * Return the sheet tab title.
     */
    public function title(): string
    {
        return $this->sheetTitle ?? 'Sheet1';
    }

    /**
     * Styling applied to this sheet.
     */
    public function styling(): StyleConfig
    {
        return $this->styleConfig;
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        return $this->columnFormats;
    }

    /**
     * @return array<string, float|int>
     */
    public function columnWidths(): array
    {
        return $this->columnWidths;
    }

    /**
     * Whether full-range styling (wrap text + auto-filter) should be applied.
     */
    public function applyFullRangeStyling(): bool
    {
        return ! $this->disableLargeSheetStyling;
    }

    /**
     * CSV/TSV writer settings (defaults when none were set).
     */
    public function csvSettings(): CsvSettings
    {
        return $this->csvSettings ?? new CsvSettings;
    }

    /**
     * The top-left cell where the sheet's data begins.
     */
    public function startCellCoordinate(): string
    {
        return $this->startCellCoordinate;
    }

    /**
     * Whether to use strict null comparison when writing cell values.
     */
    public function strictNullComparison(): bool
    {
        return $this->useStrictNulls;
    }

    /**
     * The worksheet styling callback, or null.
     *
     * @return Closure(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet): void|null
     */
    public function styleCallback(): ?Closure
    {
        return $this->styleCallback;
    }

    /**
     * The custom value binder, or null.
     */
    public function valueBinder(): ?IValueBinder
    {
        return $this->valueBinder;
    }

    /**
     * @return array<int, \PhpOffice\PhpSpreadsheet\Chart\Chart>
     */
    public function charts(): array
    {
        return $this->charts;
    }

    /**
     * @return array<int, \PhpOffice\PhpSpreadsheet\Worksheet\BaseDrawing>
     */
    public function drawings(): array
    {
        return $this->drawings;
    }

    // ──────────────────────────────────────────
    // Internal helpers
    // ──────────────────────────────────────────

    /**
     * Prepend the serial number column label to the header array.
     *
     * @param  array<int, string>  $headers
     * @return array<int, string>
     */
    protected function prependSerialNumberHeader(array $headers): array
    {
        if ($this->includeSerialNumber) {
            array_unshift($headers, $this->styleConfig->serialNumberHeader);
        }

        return $headers;
    }

    /**
     * Prepend the next serial number to a mapped row.
     *
     * @param  array<int, mixed>  $mappedRow
     * @return array<int, mixed>
     */
    protected function prependSerialNumber(array $mappedRow): array
    {
        if ($this->includeSerialNumber) {
            array_unshift($mappedRow, ++$this->serialIndex);
        }

        return $mappedRow;
    }

    /**
     * Initialize the style config from a given StyleConfig object.
     */
    protected function initializeFormatting(StyleConfig $styleConfig): void
    {
        $this->styleConfig = $styleConfig;
    }
}
