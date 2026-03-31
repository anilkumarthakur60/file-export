<?php

declare(strict_types=1);

namespace Anil\FileExport\Exports;

use Anil\FileExport\DataTransferObjects\StyleConfig;
use Anil\FileExport\Exports\Concerns\HasSheetFormatting;
use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithProperties;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Exports data from an Eloquent query builder with automatic chunking.
 *
 * Suitable for large datasets — rows are processed in configurable chunks
 * to keep memory usage constant regardless of total row count.
 *
 * @implements WithMapping<mixed>
 */
class QueryExport implements FromQuery, ShouldAutoSize, WithChunkReading, WithColumnFormatting, WithColumnWidths, WithEvents, WithHeadings, WithMapping, WithProperties, WithStyles, WithTitle
{
    use HasSheetFormatting;

    private ?int $rowLimit = null;

    /**
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed>  $builder  The query to export.
     * @param  array<int, string>  $headers  Column header labels.
     * @param  Closure  $mappingCallback  Maps each row to an array of cell values.
     * @param  int  $chunkSize  Number of rows per chunk.
     * @param  StyleConfig  $styleConfig  Styling configuration for this sheet.
     */
    public function __construct(
        private readonly EloquentBuilder|Relation $builder,
        private readonly array $headers,
        private readonly Closure $mappingCallback,
        private int $chunkSize = 1000,
        StyleConfig $styleConfig = new StyleConfig,
    ) {
        $this->initializeFormatting($styleConfig);
    }

    /**
     * Set the number of rows processed per chunk.
     */
    public function withChunkSize(int $size): static
    {
        $this->chunkSize = $size;

        return $this;
    }

    /**
     * Limit the total number of rows exported.
     */
    public function withLimit(?int $limit): static
    {
        $this->rowLimit = $limit;

        return $this;
    }

    /**
     * @return EloquentBuilder<\Illuminate\Database\Eloquent\Model>|Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed>
     */
    public function query(): EloquentBuilder|Relation
    {
        if ($this->rowLimit !== null) {
            return $this->builder->limit($this->rowLimit);
        }

        return $this->builder;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return $this->prependSerialNumberHeader($this->headers);
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

    public function chunkSize(): int
    {
        return $this->chunkSize;
    }
}
