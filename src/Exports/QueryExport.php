<?php

declare(strict_types=1);

namespace Anil\FileExport\Exports;

use Anil\FileExport\DataTransferObjects\StyleConfig;
use Anil\FileExport\Exports\Concerns\HasExportStyling;
use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;

/**
 * @implements WithMapping<mixed>
 */
class QueryExport implements FromQuery, ShouldAutoSize, WithChunkReading, WithColumnFormatting, WithEvents, WithHeadings, WithMapping, WithStyles
{
    use HasExportStyling;

    private ?int $limit = null;

    /**
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed>  $builder
     * @param  array<int, string>  $headers
     */
    public function __construct(
        private readonly EloquentBuilder|Relation $builder,
        private readonly array $headers,
        private readonly Closure $mappingCallback,
        private int $chunkSize = 1000,
        StyleConfig $styleConfig = new StyleConfig,
    ) {
        $this->initializeStyling($styleConfig);
    }

    public function withChunkSize(int $size): static
    {
        $this->chunkSize = $size;

        return $this;
    }

    public function withLimit(?int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * @return EloquentBuilder<\Illuminate\Database\Eloquent\Model>|Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed>
     */
    public function query(): EloquentBuilder|Relation
    {
        if ($this->limit !== null) {
            return $this->builder->limit($this->limit);
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
