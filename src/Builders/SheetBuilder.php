<?php

declare(strict_types=1);

namespace Anil\FileExport\Builders;

use Anil\FileExport\Contracts\Exportable;
use Anil\FileExport\DataTransferObjects\StyleConfig;
use Anil\FileExport\Exceptions\FileExportException;
use Anil\FileExport\Exports\CollectionExport;
use Anil\FileExport\Exports\QueryExport;
use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

/**
 * Fluent builder for configuring a single export sheet.
 *
 * Used internally by FileExporter for single-sheet exports, and directly
 * in addSheet() closures for multi-sheet exports.
 *
 * Example:
 *   $exporter->addSheet('Users', function (SheetBuilder $sheet) {
 *       $sheet->fromQuery(User::query())
 *           ->withHeaders(['Name', 'Email'])
 *           ->withMapping(fn ($u) => [$u->name, $u->email]);
 *   });
 */
final class SheetBuilder
{
    /** @var array<int, string> */
    private array $headers = [];

    private ?Closure $mappingCallback = null;

    /** @var EloquentBuilder<\Illuminate\Database\Eloquent\Model>|Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed>|null */
    private EloquentBuilder|Relation|null $queryBuilder = null;

    /** @var Collection<int, mixed>|null */
    private ?Collection $collection = null;

    private bool $includeSerialNumber = false;

    private ?StyleConfig $styleConfig = null;

    private ?int $chunkSize = null;

    private ?int $rowLimit = null;

    private ?string $sheetTitle = null;

    /** @var array<string, string> */
    private array $columnFormats = [];

    /** @var array<string, float|int> */
    private array $columnWidths = [];

    /** @var array<string, string> */
    private array $documentProperties = [];

    public function __construct(
        private readonly StyleConfig $defaultStyling,
        private readonly int $defaultChunkSize = 1000,
    ) {}

    // ──────────────────────────────────────────
    // Data source
    // ──────────────────────────────────────────

    /**
     * Set an Eloquent query as the data source (memory-efficient, chunked).
     *
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed>  $builder
     */
    public function fromQuery(EloquentBuilder|Relation $builder): self
    {
        $this->queryBuilder = $builder;
        $this->collection = null;

        return $this;
    }

    /**
     * Set a Collection as the data source (loads all data into memory).
     *
     * @param  Collection<int, mixed>  $collection
     */
    public function fromCollection(Collection $collection): self
    {
        $this->collection = $collection;
        $this->queryBuilder = null;

        return $this;
    }

    /**
     * Set both headers and mapping from an Exportable class.
     *
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed>|Collection<int, mixed>  $source
     */
    public function fromExportable(Exportable $exportable, EloquentBuilder|Relation|Collection $source): self
    {
        $this->headers = $exportable->headers();
        $this->mappingCallback = $exportable->map(...);

        if ($source instanceof Collection) {
            return $this->fromCollection($source);
        }

        return $this->fromQuery($source);
    }

    // ──────────────────────────────────────────
    // Column configuration
    // ──────────────────────────────────────────

    /**
     * Set the column header labels.
     *
     * @param  array<int, string>  $headers
     */
    public function withHeaders(array $headers): self
    {
        $this->headers = $headers;

        return $this;
    }

    /**
     * Set the callback that maps each data row to an array of cell values.
     */
    public function withMapping(Closure $callback): self
    {
        $this->mappingCallback = $callback;

        return $this;
    }

    // ──────────────────────────────────────────
    // Sheet configuration
    // ──────────────────────────────────────────

    /**
     * Set the sheet tab title (visible in multi-sheet workbooks).
     */
    public function withTitle(string $title): self
    {
        $this->sheetTitle = $title;

        return $this;
    }

    /**
     * Include a serial number column as the first column.
     */
    public function withSerialNumber(bool $include = true): self
    {
        $this->includeSerialNumber = $include;

        return $this;
    }

    /**
     * Override the default styling for this sheet.
     */
    public function withStyling(StyleConfig $config): self
    {
        $this->styleConfig = $config;

        return $this;
    }

    /**
     * Set the number of rows processed per chunk (query exports only).
     */
    public function withChunkSize(int $size): self
    {
        $this->chunkSize = $size;

        return $this;
    }

    /**
     * Limit the total number of rows exported.
     */
    public function withLimit(?int $limit): self
    {
        $this->rowLimit = $limit;

        return $this;
    }

    /**
     * Set column number/date formats.
     *
     * @param  array<string, string>  $formats  Keyed by column letter.
     */
    public function withColumnFormats(array $formats): self
    {
        $this->columnFormats = $formats;

        return $this;
    }

    /**
     * Set explicit column widths.
     *
     * @param  array<string, float|int>  $widths  Keyed by column letter.
     */
    public function withColumnWidths(array $widths): self
    {
        $this->columnWidths = $widths;

        return $this;
    }

    /**
     * Set document metadata properties.
     *
     * @param  array<string, string>  $properties
     */
    public function withProperties(array $properties): self
    {
        $this->documentProperties = $properties;

        return $this;
    }

    // ──────────────────────────────────────────
    // Build
    // ──────────────────────────────────────────

    /**
     * Validate configuration and build the Maatwebsite export object.
     *
     * @throws FileExportException
     */
    public function build(): CollectionExport|QueryExport
    {
        $this->validate();

        $styling = $this->styleConfig ?? $this->defaultStyling;

        /** @var Closure $mappingCallback */
        $mappingCallback = $this->mappingCallback;

        if ($this->queryBuilder !== null) {
            return $this->buildQueryExport($styling, $mappingCallback);
        }

        return $this->buildCollectionExport($styling, $mappingCallback);
    }

    /**
     * @throws FileExportException
     */
    public function validate(): void
    {
        if ($this->queryBuilder === null && $this->collection === null) {
            throw FileExportException::missingDataSource();
        }

        if ($this->headers === []) {
            throw FileExportException::missingHeaders();
        }

        if ($this->mappingCallback === null) {
            throw FileExportException::missingMapping();
        }
    }

    // ──────────────────────────────────────────
    // Internal builders
    // ──────────────────────────────────────────

    private function buildQueryExport(StyleConfig $styling, Closure $mappingCallback): QueryExport
    {
        /** @var EloquentBuilder<\Illuminate\Database\Eloquent\Model>|Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed> $builder */
        $builder = $this->queryBuilder;

        $export = new QueryExport(
            builder: $builder,
            headers: $this->headers,
            mappingCallback: $mappingCallback,
            chunkSize: $this->chunkSize ?? $this->defaultChunkSize,
            styleConfig: $styling,
        );

        $this->applySharedSettings($export);

        return $export->withLimit($this->rowLimit);
    }

    private function buildCollectionExport(StyleConfig $styling, Closure $mappingCallback): CollectionExport
    {
        $data = $this->collection ?? collect();

        if ($this->rowLimit !== null) {
            $data = $data->take($this->rowLimit);
        }

        $export = new CollectionExport(
            data: $data,
            headers: $this->headers,
            mappingCallback: $mappingCallback,
            styleConfig: $styling,
        );

        $this->applySharedSettings($export);

        return $export;
    }

    private function applySharedSettings(CollectionExport|QueryExport $export): void
    {
        $export
            ->withSerialNumber($this->includeSerialNumber)
            ->withColumnFormats($this->columnFormats)
            ->withColumnWidths($this->columnWidths)
            ->withProperties($this->documentProperties);

        if ($this->sheetTitle !== null) {
            $export->withTitle($this->sheetTitle);
        }
    }
}
