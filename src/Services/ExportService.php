<?php

declare(strict_types=1);

namespace Anil\FileExport\Services;

use Anil\FileExport\Builders\SheetBuilder;
use Anil\FileExport\Contracts\Exportable;
use Anil\FileExport\Contracts\FilenameGeneratorContract;
use Anil\FileExport\Contracts\UrlResolverContract;
use Anil\FileExport\DataTransferObjects\ExportConfig;
use Anil\FileExport\DataTransferObjects\ExportResult;
use Anil\FileExport\DataTransferObjects\StyleConfig;
use Anil\FileExport\Enums\ExportFormat;
use Anil\FileExport\Exceptions\ExportException;
use Anil\FileExport\Exports\CollectionExport;
use Anil\FileExport\Exports\MultiSheetExport;
use Anil\FileExport\Exports\QueryExport;
use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

final class ExportService
{
    private SheetBuilder $defaultSheet;

    /** @var array<int, SheetBuilder> */
    private array $sheets = [];

    private ExportFormat $format;

    private ?string $filename = null;

    private ?string $disk = null;

    /** @var array<string, string> */
    private array $documentProperties = [];

    public function __construct(
        private readonly ExportConfig $config,
        private readonly FilenameGeneratorContract $filenameGenerator,
        private readonly UrlResolverContract $urlResolver,
    ) {
        $this->format = $this->config->defaultFormat;
        $this->disk = $this->config->disk;
        $this->defaultSheet = new SheetBuilder($this->config->styling, $this->config->chunkSize);
    }

    // ──────────────────────────────────────────
    // Single-sheet data source (delegates to defaultSheet)
    // ──────────────────────────────────────────

    /**
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed>  $builder
     */
    public function fromQuery(EloquentBuilder|Relation $builder): self
    {
        $this->defaultSheet->fromQuery($builder);

        return $this;
    }

    /**
     * @param  Collection<int, mixed>  $collection
     */
    public function fromCollection(Collection $collection): self
    {
        $this->defaultSheet->fromCollection($collection);

        return $this;
    }

    /**
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed>|Collection<int, mixed>  $source
     */
    public function fromExportable(Exportable $exportable, EloquentBuilder|Relation|Collection $source): self
    {
        $this->defaultSheet->fromExportable($exportable, $source);

        return $this;
    }

    // ──────────────────────────────────────────
    // Single-sheet configuration (delegates to defaultSheet)
    // ──────────────────────────────────────────

    /**
     * @param  array<int, string>  $headers
     */
    public function withHeaders(array $headers): self
    {
        $this->defaultSheet->withHeaders($headers);

        return $this;
    }

    public function withMapping(Closure $callback): self
    {
        $this->defaultSheet->withMapping($callback);

        return $this;
    }

    public function withSerialNumber(bool $include = true): self
    {
        $this->defaultSheet->withSerialNumber($include);

        return $this;
    }

    public function withStyleConfig(StyleConfig $styleConfig): self
    {
        $this->defaultSheet->withStyleConfig($styleConfig);

        return $this;
    }

    public function withChunkSize(int $size): self
    {
        $this->defaultSheet->withChunkSize($size);

        return $this;
    }

    public function withLimit(?int $limit): self
    {
        $this->defaultSheet->withLimit($limit);

        return $this;
    }

    /**
     * @param  array<string, string>  $formats
     */
    public function withColumnFormats(array $formats): self
    {
        $this->defaultSheet->withColumnFormats($formats);

        return $this;
    }

    /**
     * @param  array<string, float|int>  $widths
     */
    public function withColumnWidths(array $widths): self
    {
        $this->defaultSheet->withColumnWidths($widths);

        return $this;
    }

    // ──────────────────────────────────────────
    // Multi-sheet
    // ──────────────────────────────────────────

    /**
     * Add a sheet using a closure that receives a SheetBuilder.
     *
     * @param  Closure(SheetBuilder): void  $configurator
     */
    public function addSheet(string $title, Closure $configurator): self
    {
        $sheet = new SheetBuilder($this->config->styling, $this->config->chunkSize);
        $sheet->title($title);
        $configurator($sheet);
        $this->sheets[] = $sheet;

        return $this;
    }

    // ──────────────────────────────────────────
    // File-level configuration
    // ──────────────────────────────────────────

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

    /**
     * @param  array<string, string>  $properties  e.g. ['title' => '...', 'creator' => '...', 'description' => '...']
     */
    public function withDocumentProperties(array $properties): self
    {
        $this->documentProperties = $properties;

        return $this;
    }

    // ──────────────────────────────────────────
    // Output methods
    // ──────────────────────────────────────────

    /**
     * Store the export to disk and return an ExportResult.
     *
     * @throws ExportException
     */
    public function store(): ExportResult
    {
        $exportClass = $this->buildExportClass();
        $filename = $this->filenameGenerator->generate($this->filename, $this->format);
        $path = $this->config->path.'/'.$filename;
        $disk = $this->disk ?? $this->config->disk;

        $stored = Excel::store($exportClass, $path, $disk, $this->format->writerType());

        if (! $stored) {
            throw ExportException::storageFailed($filename);
        }

        $url = $this->urlResolver->resolve($disk, $path);

        return new ExportResult(
            filename: $filename,
            url: $url,
            path: $path,
            disk: $disk,
        );
    }

    /**
     * Download the export directly as a browser response.
     *
     * @throws ExportException
     */
    public function download(): BinaryFileResponse
    {
        $exportClass = $this->buildExportClass();
        $filename = $this->filenameGenerator->generate($this->filename, $this->format);

        /** @var BinaryFileResponse */
        return Excel::download($exportClass, $filename, $this->format->writerType());
    }

    /**
     * Queue the export for background processing.
     *
     * @throws ExportException
     */
    public function queue(): ExportResult
    {
        $exportClass = $this->buildExportClass();
        $filename = $this->filenameGenerator->generate($this->filename, $this->format);
        $path = $this->config->path.'/'.$filename;
        $disk = $this->disk ?? $this->config->disk;

        Excel::queue($exportClass, $path, $disk, $this->format->writerType());

        return new ExportResult(
            filename: $filename,
            path: $path,
            disk: $disk,
        );
    }

    /**
     * Get the raw binary content of the export.
     *
     * @throws ExportException
     */
    public function raw(): string
    {
        $exportClass = $this->buildExportClass();

        return Excel::raw($exportClass, $this->format->writerType());
    }

    // ──────────────────────────────────────────
    // Internal
    // ──────────────────────────────────────────

    /**
     * @throws ExportException
     */
    private function buildExportClass(): CollectionExport|QueryExport|MultiSheetExport
    {
        if ($this->sheets !== []) {
            return $this->buildMultiSheetExport();
        }

        return $this->defaultSheet->build();
    }

    /**
     * @throws ExportException
     */
    private function buildMultiSheetExport(): MultiSheetExport
    {
        $builtSheets = [];

        foreach ($this->sheets as $sheet) {
            $builtSheets[] = $sheet->build();
        }

        return new MultiSheetExport(
            sheets: $builtSheets,
            documentProperties: $this->documentProperties,
        );
    }
}
