<?php

declare(strict_types=1);

namespace Anil\FileExport\Exports\Concerns;

use Anil\FileExport\DataTransferObjects\StyleConfig;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

trait HasExportStyling
{
    protected StyleConfig $styleConfig;

    protected bool $includeSerialNumber = false;

    private int $serialIndex = 0;

    /** @var array<string, string> */
    protected array $columnFormats = [];

    private ?string $sheetTitle = null;

    /** @var array<string, float|int> */
    private array $columnWidths = [];

    /** @var array<string, string> */
    private array $properties = [];

    public function withSerialNumber(bool $include = true): static
    {
        $this->includeSerialNumber = $include;

        return $this;
    }

    public function withStyleConfig(StyleConfig $config): static
    {
        $this->styleConfig = $config;

        return $this;
    }

    /**
     * @param  array<string, string>  $formats
     */
    public function withColumnFormats(array $formats): static
    {
        $this->columnFormats = $formats;

        return $this;
    }

    public function withTitle(string $title): static
    {
        $this->sheetTitle = $title;

        return $this;
    }

    /**
     * @param  array<string, float|int>  $widths  e.g. ['A' => 20, 'B' => 30.5]
     */
    public function withColumnWidths(array $widths): static
    {
        $this->columnWidths = $widths;

        return $this;
    }

    /**
     * @param  array<string, string>  $properties  e.g. ['title' => 'Report', 'creator' => 'System']
     */
    public function withProperties(array $properties): static
    {
        $this->properties = $properties;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        return $this->columnFormats;
    }

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
        return $this->properties;
    }

    /**
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

    /**
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
     * @param  array<int, mixed>  $mapped
     * @return array<int, mixed>
     */
    protected function prependSerialNumber(array $mapped): array
    {
        if ($this->includeSerialNumber) {
            array_unshift($mapped, ++$this->serialIndex);
        }

        return $mapped;
    }

    protected function initializeStyling(StyleConfig $styleConfig): void
    {
        $this->styleConfig = $styleConfig;
    }
}
