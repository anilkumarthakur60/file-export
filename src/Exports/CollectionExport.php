<?php

declare(strict_types=1);

namespace Anil\FileExport\Exports;

use Anil\FileExport\DataTransferObjects\StyleConfig;
use Anil\FileExport\Exports\Concerns\HasSheetFormatting;
use Closure;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithProperties;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Exports data from an in-memory Collection.
 *
 * Suitable for small-to-medium datasets that are already loaded in memory.
 * For large datasets, use QueryExport with automatic chunking instead.
 *
 * @implements WithMapping<mixed>
 */
class CollectionExport implements FromCollection, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithHeadings, WithMapping, WithProperties, WithStyles, WithTitle
{
    use HasSheetFormatting;

    /**
     * @param  Collection<int, mixed>  $data  The data to export.
     * @param  array<int, string>  $headers  Column header labels.
     * @param  Closure  $mappingCallback  Maps each row to an array of cell values.
     * @param  StyleConfig  $styleConfig  Styling configuration for this sheet.
     */
    public function __construct(
        private readonly Collection $data,
        private readonly array $headers,
        private readonly Closure $mappingCallback,
        StyleConfig $styleConfig = new StyleConfig,
    ) {
        $this->initializeFormatting($styleConfig);
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return $this->prependSerialNumberHeader($this->headers);
    }

    /**
     * @return Collection<int, mixed>
     */
    public function collection(): Collection
    {
        return $this->data;
    }

    /**
     * @return array<int, mixed>
     */
    public function map(mixed $row): array
    {
        /** @var array<int, mixed> $mapped */
        $mapped = ($this->mappingCallback)($row);

        return $this->prependSerialNumber($mapped);
    }
}
