<?php

declare(strict_types=1);

namespace Anil\FileExport\Exports;

use Anil\FileExport\DataTransferObjects\StyleConfig;
use Anil\FileExport\Exports\Concerns\HasSheetFormatting;
use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Laravel\SerializableClosure\SerializableClosure;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
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
 * Shared implementation for query-backed exports with automatic chunking.
 *
 * Rows are processed in configurable chunks to keep memory usage constant
 * regardless of total row count. Subclasses decide whether to opt into
 * auto-column-sizing (see QueryExport vs LargeQueryExport).
 *
 * The query source may be a live builder (convenient for sync exports) or a
 * factory closure that rebuilds the query (required for queued exports, where
 * a live builder cannot be serialized because it holds a database connection).
 * Both the query factory and the mapping callback are wrapped in a
 * SerializableClosure so the export can be queued.
 *
 * @implements WithMapping<mixed>
 */
abstract class BaseQueryExport implements FromQuery, WithChunkReading, WithColumnFormatting, WithColumnWidths, WithCustomCsvSettings, WithEvents, WithHeadings, WithMapping, WithProperties, WithStyles, WithTitle
{
    use HasSheetFormatting;

    private ?int $rowLimit = null;

    private ?int $rowOffset = null;

    /**
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed>|(Closure(): (EloquentBuilder<\Illuminate\Database\Eloquent\Model>|Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed>))  $builder  The query to export, or a factory that returns it.
     * @param  array<int, string>  $headers  Column header labels.
     * @param  Closure  $mappingCallback  Maps each row to an array of cell values.
     * @param  int  $chunkSize  Number of rows per chunk.
     * @param  StyleConfig  $styleConfig  Styling configuration for this sheet.
     */
    public function __construct(
        private EloquentBuilder|Relation|Closure $builder,
        private readonly array $headers,
        private Closure $mappingCallback,
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
     * Skip a number of leading rows (used for auto-split windowing).
     */
    public function withOffset(?int $offset): static
    {
        $this->rowOffset = $offset;

        return $this;
    }

    /**
     * @return EloquentBuilder<\Illuminate\Database\Eloquent\Model>|Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed>
     */
    public function query(): EloquentBuilder|Relation
    {
        $query = $this->builder instanceof Closure ? ($this->builder)() : $this->builder;

        if ($this->rowOffset !== null) {
            $query = $query->offset($this->rowOffset);
        }

        if ($this->rowLimit !== null) {
            $query = $query->limit($this->rowLimit);
        }

        return $query;
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

    /**
     * Capture state for queueing, wrapping closures so they can be serialized.
     *
     * @return array<mixed>
     */
    public function __serialize(): array
    {
        $data = get_object_vars($this);

        $data['mappingCallback'] = new SerializableClosure($this->mappingCallback);

        if ($this->builder instanceof Closure) {
            $data['builder'] = new SerializableClosure($this->builder);
        }

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
