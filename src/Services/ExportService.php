<?php

declare(strict_types=1);

namespace Anil\FileExport\Services;

use Anil\FileExport\Contracts\Exportable;
use Anil\FileExport\Contracts\FilenameGeneratorContract;
use Anil\FileExport\Contracts\UrlResolverContract;
use Anil\FileExport\DataTransferObjects\ExportConfig;
use Anil\FileExport\DataTransferObjects\StyleConfig;
use Anil\FileExport\Enums\ExportFormat;
use Anil\FileExport\Exceptions\ExportException;
use Anil\FileExport\Exports\CollectionExport;
use Anil\FileExport\Exports\QueryExport;
use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

final class ExportService
{
    /** @var array<int, string> */
    private array $headers = [];

    private ?Closure $mappingCallback = null;

    /** @var EloquentBuilder<\Illuminate\Database\Eloquent\Model>|Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed>|null */
    private EloquentBuilder|Relation|null $builder = null;

    /** @var Collection<int, mixed>|null */
    private ?Collection $collection = null;

    private ExportFormat $format;

    private ?string $filename = null;

    private ?string $disk = null;

    private bool $includeSerialNumber = false;

    private ?StyleConfig $styleConfig = null;

    private ?int $chunkSize = null;

    private ?int $limit = null;

    /** @var array<string, string> */
    private array $columnFormats = [];

    public function __construct(
        private readonly ExportConfig $config,
        private readonly FilenameGeneratorContract $filenameGenerator,
        private readonly UrlResolverContract $urlResolver,
    ) {
        $this->format = $this->config->defaultFormat;
        $this->disk = $this->config->disk;
    }

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

    public function format(ExportFormat $format): self
    {
        $this->format = $format;

        return $this;
    }

    public function filename(string $filename): self
    {
        $this->filename = $filename;

        return $this;
    }

    public function disk(string $disk): self
    {
        $this->disk = $disk;

        return $this;
    }

    public function withSerialNumber(bool $include = true): self
    {
        $this->includeSerialNumber = $include;

        return $this;
    }

    public function withStyleConfig(StyleConfig $styleConfig): self
    {
        $this->styleConfig = $styleConfig;

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
     * @throws ExportException
     */
    public function store(): JsonResponse
    {
        $this->validate();

        $exportClass = $this->buildExportClass();
        $filename = $this->filenameGenerator->generate($this->filename, $this->format);
        $path = $this->config->path.'/'.$filename;
        $disk = $this->disk ?? $this->config->disk;

        $stored = Excel::store($exportClass, $path, $disk, $this->format->writerType());

        if (! $stored) {
            throw ExportException::storageFailed($filename);
        }

        $url = $this->urlResolver->resolve($disk, $path);

        return new JsonResponse([
            'success' => true,
            'url' => $url,
            'filename' => $filename,
            'message' => 'File exported successfully.',
        ]);
    }

    /**
     * @throws ExportException
     */
    public function download(): BinaryFileResponse
    {
        $this->validate();

        $exportClass = $this->buildExportClass();
        $filename = $this->filenameGenerator->generate($this->filename, $this->format);

        /** @var BinaryFileResponse */
        return Excel::download($exportClass, $filename, $this->format->writerType());
    }

    /**
     * @throws ExportException
     */
    public function queue(): JsonResponse
    {
        $this->validate();

        $exportClass = $this->buildExportClass();
        $filename = $this->filenameGenerator->generate($this->filename, $this->format);
        $path = $this->config->path.'/'.$filename;
        $disk = $this->disk ?? $this->config->disk;

        Excel::queue($exportClass, $path, $disk, $this->format->writerType());

        return new JsonResponse([
            'success' => true,
            'filename' => $filename,
            'message' => 'Export has been queued for processing.',
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * @throws ExportException
     */
    private function validate(): void
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

    private function buildExportClass(): CollectionExport|QueryExport
    {
        $styleConfig = $this->styleConfig ?? $this->config->styling;

        if ($this->builder !== null) {
            return $this->buildQueryExport($styleConfig);
        }

        return $this->buildCollectionExport($styleConfig);
    }

    private function buildQueryExport(StyleConfig $styleConfig): QueryExport
    {
        /** @var Closure $mappingCallback */
        $mappingCallback = $this->mappingCallback;

        /** @var EloquentBuilder<\Illuminate\Database\Eloquent\Model>|Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed> $builder */
        $builder = $this->builder;

        $export = new QueryExport(
            builder: $builder,
            headers: $this->headers,
            mappingCallback: $mappingCallback,
            chunkSize: $this->chunkSize ?? $this->config->chunkSize,
            styleConfig: $styleConfig,
        );

        return $export
            ->withSerialNumber($this->includeSerialNumber)
            ->withColumnFormats($this->columnFormats)
            ->withLimit($this->limit);
    }

    private function buildCollectionExport(StyleConfig $styleConfig): CollectionExport
    {
        /** @var Closure $mappingCallback */
        $mappingCallback = $this->mappingCallback;

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

        return $export
            ->withSerialNumber($this->includeSerialNumber)
            ->withColumnFormats($this->columnFormats);
    }
}
