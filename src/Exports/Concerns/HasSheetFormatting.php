<?php

declare(strict_types=1);

namespace Anil\FileExport\Exports\Concerns;

use Anil\FileExport\DataTransferObjects\StyleConfig;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Provides shared formatting logic for export classes.
 *
 * Implements the Maatwebsite concerns: WithStyles, WithEvents,
 * WithColumnFormatting, WithColumnWidths, WithTitle, WithProperties.
 *
 * Used by CollectionExport and QueryExport to eliminate code duplication.
 */
trait HasSheetFormatting
{
    protected StyleConfig $styleConfig;

    protected bool $includeSerialNumber = false;

    private int $serialIndex = 0;

    private ?string $sheetTitle = null;

    /** @var array<string, string> */
    protected array $columnFormats = [];

    /** @var array<string, float|int> */
    private array $columnWidths = [];

    /** @var array<string, string> */
    private array $documentProperties = [];

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
     * Set column number/date formats using PhpSpreadsheet format constants.
     *
     * @param  array<string, string>  $formats  Keyed by column letter (e.g., ['A' => NumberFormat::FORMAT_DATE_DDMMYYYY]).
     */
    public function withColumnFormats(array $formats): static
    {
        $this->columnFormats = $formats;

        return $this;
    }

    /**
     * Set the sheet tab title (visible in multi-sheet workbooks).
     */
    public function withTitle(string $title): static
    {
        $this->sheetTitle = $title;

        return $this;
    }

    /**
     * Set explicit column widths. Overrides auto-size for specified columns.
     *
     * @param  array<string, float|int>  $widths  Keyed by column letter (e.g., ['A' => 20, 'B' => 35.5]).
     */
    public function withColumnWidths(array $widths): static
    {
        $this->columnWidths = $widths;

        return $this;
    }

    /**
     * Set document metadata properties (title, creator, company, description, etc.).
     *
     * @param  array<string, string>  $properties  Keyed by property name (e.g., ['title' => 'Report', 'creator' => 'System']).
     */
    public function withProperties(array $properties): static
    {
        $this->documentProperties = $properties;

        return $this;
    }

    // ──────────────────────────────────────────
    // Maatwebsite concern implementations
    // ──────────────────────────────────────────

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        return $this->columnFormats;
    }

    /**
     * Return the sheet tab title.
     */
    public function title(): string
    {
        return $this->sheetTitle ?? 'Sheet1';
    }

    /**
     * @return array<string, float|int>
     */
    public function columnWidths(): array
    {
        return $this->columnWidths;
    }

    /**
     * @return array<string, string>
     */
    public function properties(): array
    {
        return $this->documentProperties;
    }

    /**
     * Apply header styling: bold, background color, font color, alignment.
     *
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        /** @var array<string, array<string, mixed>> $headerStyle */
        $headerStyle = [];

        /** @var array<string, mixed> $font */
        $font = [];

        if ($this->styleConfig->headerBold) {
            $font['bold'] = true;
        }

        if ($this->styleConfig->headerFontSize !== 12) {
            $font['size'] = $this->styleConfig->headerFontSize;
        }

        if ($this->styleConfig->headerFontColor !== '') {
            $font['color'] = ['argb' => $this->styleConfig->headerFontColor];
        }

        if ($font !== []) {
            $headerStyle['font'] = $font;
        }

        if ($this->styleConfig->headerBackground !== '') {
            $headerStyle['fill'] = [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => $this->styleConfig->headerBackground],
            ];
        }

        $headerStyle['alignment'] = [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ];

        return [1 => $headerStyle];
    }

    /**
     * Register after-sheet events: text wrapping, header freeze, auto-filter.
     *
     * @return array<string, \Closure>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();

                if ($this->styleConfig->wrapText) {
                    $sheet->getStyle("A1:{$highestColumn}{$highestRow}")
                        ->getAlignment()
                        ->setWrapText(true)
                        ->setVertical(Alignment::VERTICAL_TOP);
                }

                if ($this->styleConfig->freezeHeader) {
                    $sheet->freezePane('A2');
                }

                if ($this->styleConfig->autoFilter) {
                    $sheet->setAutoFilter("A1:{$highestColumn}1");
                }
            },
        ];
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
