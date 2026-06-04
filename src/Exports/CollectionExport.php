<?php

declare(strict_types=1);

namespace Anil\FileExport\Exports;

use Anil\FileExport\DataTransferObjects\StyleConfig;
use Anil\FileExport\Exports\Concerns\HasSheetFormatting;
use Closure;
use Illuminate\Support\Collection;
use Laravel\SerializableClosure\SerializableClosure;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
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
class CollectionExport implements FromCollection, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithCustomCsvSettings, WithEvents, WithHeadings, WithMapping, WithProperties, WithStyles, WithTitle
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
        private Closure $mappingCallback,
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

    /**
     * Capture state for queueing, wrapping the mapping closure so it serializes.
     *
     * @return array<mixed>
     */
    public function __serialize(): array
    {
        $data = get_object_vars($this);

        $data['mappingCallback'] = new SerializableClosure($this->mappingCallback);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function __unserialize(array $data): void
    {
        foreach ($data as $key => $value) {
            if ($value instanceof SerializableClosure) {
                $value = $value->getClosure();
            }

            $this->{$key} = $value;
        }
    }
}

