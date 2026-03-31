<?php

declare(strict_types=1);

namespace Anil\FileExport\Builders;

use Anil\FileExport\Contracts\Exportable;
use Anil\FileExport\DataTransferObjects\StyleConfig;
use Anil\FileExport\Exceptions\ExportException;
use Anil\FileExport\Exports\CollectionExport;
use Anil\FileExport\Exports\QueryExport;
use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

final class SheetBuilder
{
    /** @var array<int, string> */
    private array $headers = [];

    private ?Closure $mappingCallback = null;

    /** @var EloquentBuilder<\Illuminate\Database\Eloquent\Model>|Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed>|null */
    private EloquentBuilder|Relation|null $builder = null;

    /** @var Collection<int, mixed>|null */
    private ?Collection $collection = null;

    private bool $includeSerialNumber = false;

    private ?StyleConfig $styleConfig = null;

    private ?int $chunkSize = null;

    private ?int $limit = null;

    /** @var array<string, string> */
    private array $columnFormats = [];

    /** @var array<string, float|int> */
    private array $columnWidths = [];

    private ?string $title = null;

    /** @var array<string, string> */
    private array $properties = [];

    public function __construct(
        private readonly StyleConfig $defaultStyle,
        private readonly int $defaultChunkSize = 1000,
    ) {}

    /**
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed>  $builder
     */
    public function fromQuery(EloquentBuilder|Relation $builder): self
    {
        $this->builder = $builder;
        $this->collection = null;

        return $this;
    }

    /**
     * @param  Collection<int, mixed>  $collection
     */
    public function fromCollection(Collection $collection): self
    {
        $this->collection = $collection;
        $this->builder = null;

        return $this;
    }

    /**
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

    /**
     * @param  array<int, string>  $headers
     */
    public function withHeaders(array $headers): self
    {
        $this->headers = $headers;

        return $this;
    }

    public function withMapping(Closure $callback): self
    {
        $this->mappingCallback = $callback;

        return $this;
    }

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function withSerialNumber(bool $include = true): self
    {
        $this->includeSerialNumber = $include;

        return $this;
    }

    public function withStyleConfig(StyleConfig $config): self
    {
        $this->styleConfig = $config;

        return $this;
    }

    public function withChunkSize(int $size): self
    {
        $this->chunkSize = $size;

        return $this;
    }

    public function withLimit(?int $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * @param  array<string, string>  $formats
     */
    public function withColumnFormats(array $formats): self
    {
        $this->columnFormats = $formats;

        return $this;
    }

    /**
     * @param  array<string, float|int>  $widths
     */
    public function withColumnWidths(array $widths): self
    {
        $this->columnWidths = $widths;

        return $this;
    }

    /**
     * @param  array<string, string>  $properties
     */
    public function withProperties(array $properties): self
    {
        $this->properties = $properties;

        return $this;
    }

    /**
     * @throws ExportException
     */
    public function validate(): void
    {
        if ($this->builder === null && $this->collection === null) {
            throw ExportException::missingDataSource();
        }

        if ($this->headers === []) {
            throw ExportException::missingHeaders();
        }

        if ($this->mappingCallback === null) {
            throw ExportException::missingMapping();
        }
    }

    /**
     * @throws ExportException
     */
    public function build(): CollectionExport|QueryExport
    {
        $this->validate();

        $styleConfig = $this->styleConfig ?? $this->defaultStyle;

        /** @var Closure $mappingCallback */
        $mappingCallback = $this->mappingCallback;

        if ($this->builder !== null) {
            return $this->buildQueryExport($styleConfig, $mappingCallback);
        }

        return $this->buildCollectionExport($styleConfig, $mappingCallback);
    }

    private function buildQueryExport(StyleConfig $styleConfig, Closure $mappingCallback): QueryExport
    {
        /** @var EloquentBuilder<\Illuminate\Database\Eloquent\Model>|Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed> $builder */
        $builder = $this->builder;

        $export = new QueryExport(
            builder: $builder,
            headers: $this->headers,
            mappingCallback: $mappingCallback,
            chunkSize: $this->chunkSize ?? $this->defaultChunkSize,
            styleConfig: $styleConfig,
        );

        $this->applySharedConfig($export);

        return $export->withLimit($this->limit);
    }

    private function buildCollectionExport(StyleConfig $styleConfig, Closure $mappingCallback): CollectionExport
    {
        $data = $this->collection ?? collect();

        if ($this->limit !== null) {
            $data = $data->take($this->limit);
        }

        $export = new CollectionExport(
            data: $data,
            headers: $this->headers,
            mappingCallback: $mappingCallback,
            styleConfig: $styleConfig,
        );

        $this->applySharedConfig($export);

        return $export;
    }

    private function applySharedConfig(CollectionExport|QueryExport $export): void
    {
        $export
            ->withSerialNumber($this->includeSerialNumber)
            ->withColumnFormats($this->columnFormats)
            ->withColumnWidths($this->columnWidths)
            ->withProperties($this->properties);

        if ($this->title !== null) {
            $export->withTitle($this->title);
        }
    }
}
