<?php

declare(strict_types=1);

namespace Anil\FileExport\Builders;

use Anil\FileExport\Contracts\Exportable;
use Anil\FileExport\DataTransferObjects\CsvSettings;
use Anil\FileExport\DataTransferObjects\StyleConfig;
use Anil\FileExport\Enums\ExportFormat;
use Anil\FileExport\Exceptions\FileExportException;
use Anil\FileExport\Exports\CollectionExport;
use Anil\FileExport\Exports\LargeQueryExport;
use Anil\FileExport\Exports\MultiSheetExport;
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

    /** @var EloquentBuilder<\Illuminate\Database\Eloquent\Model>|Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed>|(Closure(): (EloquentBuilder<\Illuminate\Database\Eloquent\Model>|Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed>))|null */
    private EloquentBuilder|Relation|Closure|null $queryBuilder = null;

    /** @var Collection<int, mixed>|null */
    private ?Collection $collection = null;

    private bool $includeSerialNumber = false;

    private ?StyleConfig $styleConfig = null;

    private ?CsvSettings $csvSettings = null;

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
        private readonly ?int $maxRows = 1000000,
        private readonly bool $disableAutoSizeOnLargeSheets = true,
        private readonly bool $disableStylingOnLargeSheets = true,
        private readonly CsvSettings $defaultCsv = new CsvSettings,
    ) {}

    // ──────────────────────────────────────────
    // Data source
    // ──────────────────────────────────────────

    /**
     * Set an Eloquent query as the data source (memory-efficient, chunked).
     *
     * Pass a factory closure (e.g. fn () => User::query()) instead of a live
     * builder when the export will be queued — a live builder holds a database
     * connection that cannot be serialized onto a queue.
     *
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed>|(Closure(): (EloquentBuilder<\Illuminate\Database\Eloquent\Model>|Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed>))  $builder
     */
    public function fromQuery(EloquentBuilder|Relation|Closure $builder): self
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
     * Whether this sheet's source is a live (non-serializable) query builder.
     *
     * Used to reject queued exports built from a live builder, which holds a
     * database connection that cannot be serialized onto a queue.
     */
    public function usesLiveQueryBuilder(): bool
    {
        return $this->queryBuilder instanceof EloquentBuilder
            || $this->queryBuilder instanceof Relation;
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
     * Override the CSV/TSV writer settings for this sheet.
     */
    public function withCsvSettings(CsvSettings $settings): self
    {
        $this->csvSettings = $settings;

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
     * Validate, enforce the row limit, and build the export — auto-splitting
     * oversized query exports across multiple sheets when the format requires.
     *
     * Used for single-sheet exports, where the workbook format is known and
     * the package may transparently split or guard against oversized output.
     * Multi-sheet workbooks use build() per sheet instead (no auto-split).
     *
     * @param  bool  $autoSplitEnabled  Whether oversized xlsx/ods query exports may split across sheets.
     * @param  int  $rowsPerSheet  Maximum rows per sheet before splitting.
     *
     * @throws FileExportException
     */
    public function buildResolved(ExportFormat $format, bool $autoSplitEnabled, int $rowsPerSheet): CollectionExport|QueryExport|MultiSheetExport
    {
        $this->validate();

        $styling = $this->styleConfig ?? $this->defaultStyling;

        /** @var Closure $mappingCallback */
        $mappingCallback = $this->mappingCallback;

        // Collection sources are already in memory: guard the row limit but
        // never split (a collection that large is itself the problem).
        if ($this->queryBuilder === null) {
            $total = $this->collection?->count() ?? 0;
            if ($this->rowLimit !== null) {
                $total = min($total, $this->rowLimit);
            }
            $this->enforceMaxRows($total, $format);

            return $this->buildCollectionExport($styling, $mappingCallback);
        }

        $total = $this->countQueryRows($this->queryBuilder);
        if ($this->rowLimit !== null) {
            $total = min($total, $this->rowLimit);
        }

        $shouldSplit = $autoSplitEnabled
            && $format->hasRowLimit()
            && $total > $rowsPerSheet;

        if ($shouldSplit) {
            if ($format === ExportFormat::PDF) {
                throw FileExportException::autoSplitUnsupported($format->value);
            }

            return $this->buildSplitExport($styling, $mappingCallback, $this->queryBuilder, $total, $rowsPerSheet);
        }

        $this->enforceMaxRows($total, $format);

        return $this->buildQueryExport($styling, $mappingCallback);
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
        $builder = $this->queryBuilder ?? throw FileExportException::missingDataSource();

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
            ->withProperties($this->documentProperties)
            ->withCsvSettings($this->csvSettings ?? $this->defaultCsv);

        if ($this->sheetTitle !== null) {
            $export->withTitle($this->sheetTitle);
        }
    }

    /**
     * Throw if the resolved row count exceeds the configured maximum.
     *
     * Only formats with a per-sheet row limit (xlsx/ods/pdf) are guarded —
     * CSV/TSV/HTML stream unbounded. Auto-split is the sanctioned way to
     * exceed the limit, so this is never reached when splitting occurs.
     *
     * @throws FileExportException
     */
    private function enforceMaxRows(int $total, ExportFormat $format): void
    {
        if ($this->maxRows !== null && $format->hasRowLimit() && $total > $this->maxRows) {
            throw FileExportException::rowLimitExceeded($total, $this->maxRows);
        }
    }

    /**
     * Count the rows a query would produce, without mutating the user's builder.
     *
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed>|(Closure(): (EloquentBuilder<\Illuminate\Database\Eloquent\Model>|Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed>))  $builder
     */
    private function countQueryRows(EloquentBuilder|Relation|Closure $builder): int
    {
        // A factory closure already yields a fresh builder; a live builder is
        // cloned so the count query never mutates the caller's instance.
        $countable = $builder instanceof Closure ? $builder() : clone $builder;

        if ($countable instanceof Relation) {
            return $countable->getQuery()->toBase()->getCountForPagination();
        }

        return $countable->toBase()->getCountForPagination();
    }

    /**
     * Split a query across multiple sheets, each capped at rowsPerSheet rows.
     *
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed>|(Closure(): (EloquentBuilder<\Illuminate\Database\Eloquent\Model>|Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed>))  $builder
     */
    private function buildSplitExport(StyleConfig $styling, Closure $mappingCallback, EloquentBuilder|Relation|Closure $builder, int $total, int $cap): MultiSheetExport
    {
        $sheetCount = (int) ceil($total / $cap);

        $windows = [];
        for ($index = 0; $index < $sheetCount; $index++) {
            $offset = $index * $cap;
            $limit = min($cap, $total - $offset);
            $windows[] = $this->buildQueryWindow($builder, $styling, $mappingCallback, $offset, $limit, $index, $cap);
        }

        return new MultiSheetExport(
            sheets: $windows,
            documentProperties: $this->documentProperties,
        );
    }

    /**
     * Build a single windowed sheet (offset/limit slice) for an auto-split export.
     *
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed>|(Closure(): (EloquentBuilder<\Illuminate\Database\Eloquent\Model>|Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed>))  $builder
     */
    private function buildQueryWindow(EloquentBuilder|Relation|Closure $builder, StyleConfig $styling, Closure $mappingCallback, int $offset, int $limit, int $index, int $cap): QueryExport|LargeQueryExport
    {
        // A factory closure yields a fresh builder per query() call, so it can be
        // shared across windows; a live builder is cloned so each window's
        // offset/limit don't leak into its siblings.
        $windowBuilder = $builder instanceof Closure ? $builder : clone $builder;
        $chunkSize = $this->chunkSize ?? $this->defaultChunkSize;

        $export = $this->disableAutoSizeOnLargeSheets
            ? new LargeQueryExport($windowBuilder, $this->headers, $mappingCallback, $chunkSize, $styling)
            : new QueryExport($windowBuilder, $this->headers, $mappingCallback, $chunkSize, $styling);

        $export
            ->withOffset($offset)
            ->withLimit($limit)
            ->withSerialNumber($this->includeSerialNumber)
            ->withColumnFormats($this->columnFormats)
            ->withColumnWidths($this->columnWidths)
            ->withProperties($this->documentProperties)
            ->withCsvSettings($this->csvSettings ?? $this->defaultCsv)
            ->disableLargeSheetStyling($this->disableStylingOnLargeSheets);

        if ($this->includeSerialNumber) {
            // Continue numbering where the previous sheet left off.
            $export->withSerialStart($index * $cap);
        }

        $title = $this->sheetTitle !== null
            ? $this->sheetTitle.' '.($index + 1)
            : 'Sheet'.($index + 1);

        $export->withTitle($title);

        return $export;
    }
}
