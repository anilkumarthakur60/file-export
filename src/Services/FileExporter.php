<?php

declare(strict_types=1);

namespace Anil\FileExport\Services;

use Anil\FileExport\Builders\SheetBuilder;
use Anil\FileExport\Contracts\Exportable;
use Anil\FileExport\Contracts\FilenameGenerator;
use Anil\FileExport\Contracts\UrlResolver;
use Anil\FileExport\DataTransferObjects\CompletionConfig;
use Anil\FileExport\DataTransferObjects\CsvSettings;
use Anil\FileExport\DataTransferObjects\ExportConfig;
use Anil\FileExport\DataTransferObjects\ExportResult;
use Anil\FileExport\DataTransferObjects\StyleConfig;
use Anil\FileExport\Engine\Contracts\ExportEngine;
use Anil\FileExport\Engine\ExportDocument;
use Anil\FileExport\Enums\ExportFormat;
use Anil\FileExport\Exceptions\FileExportException;
use Anil\FileExport\Jobs\PerformExport;
use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use Laravel\SerializableClosure\SerializableClosure;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The main entry point for creating file exports.
 *
 * Provides a fluent API for configuring and executing exports. Supports
 * single-sheet and multi-sheet workbooks, six output formats, and four
 * delivery modes (store, download, queue, raw).
 *
 * All external dependencies are injected via constructor, making this
 * class fully testable with mocks.
 *
 * Usage:
 *   $exporter->fromQuery(User::query())
 *       ->withHeaders(['Name', 'Email'])
 *       ->withMapping(fn ($u) => [$u->name, $u->email])
 *       ->store();
 */
final class FileExporter
{
    use Conditionable;
    use Macroable;

    /** @var SheetBuilder The builder for single-sheet exports. */
    private SheetBuilder $defaultSheet;

    /** @var array<int, SheetBuilder> Builders for multi-sheet exports. */
    private array $sheets = [];

    private ExportFormat $format;

    private ?string $filename = null;

    private ?string $disk = null;

    // Runtime overrides for config values (null = fall back to config).
    private ?string $pathOverride = null;

    private bool $maxRowsOverridden = false;

    private ?int $maxRowsOverride = null;

    private ?int $expireDaysOverride = null;

    private ?string $mailSubjectOverride = null;

    private ?int $mailAttachThresholdOverride = null;

    /** @var array<string, string> */
    private array $documentProperties = [];

    private ?Closure $defaultStyleCallback = null;

    /** @var string|array<string, mixed>|null */
    private string|array|null $background = null;

    private bool $precalculateFormulas = false;

    // Completion options (apply to store() and queue()).
    private bool $fireCompletionEvent = true;

    private ?object $notifiable = null;

    private ?Notification $notification = null;

    private ?Closure $completionCallback = null;

    /** @var array<int, string> */
    private array $mailRecipients = [];

    // Auto-split overrides (null = fall back to config).
    private ?bool $autoSplitOverride = null;

    private ?int $rowsPerSheetOverride = null;

    // Queue targeting for queued exports.
    private ?string $queueConnection = null;

    private ?string $queueName = null;

    /**
     * @param  ExportConfig  $config  Package configuration (resolved from config file).
     * @param  FilenameGenerator  $filenameGenerator  Generates filenames for stored exports.
     * @param  UrlResolver  $urlResolver  Resolves download URLs for stored exports.
     * @param  ExportEngine  $engine  The rendering engine that writes the export.
     * @param  ExportCompletionHandler  $completionHandler  Runs post-export actions (event/notify/callback/email).
     */
    public function __construct(
        private readonly ExportConfig $config,
        private readonly FilenameGenerator $filenameGenerator,
        private readonly UrlResolver $urlResolver,
        private readonly ExportEngine $engine,
        private readonly ExportCompletionHandler $completionHandler,
    ) {
        $this->format = $this->config->defaultFormat;
        $this->disk = $this->config->disk;
        $this->queueConnection = $this->config->queueConnection;
        $this->queueName = $this->config->queueName;
        $this->defaultSheet = $this->createSheetBuilder();

        // Apply default document properties from config
        if ($this->config->defaultCreator !== '') {
            $this->documentProperties['creator'] = $this->config->defaultCreator;
        }
        if ($this->config->defaultCompany !== '') {
            $this->documentProperties['company'] = $this->config->defaultCompany;
        }
    }

    // ──────────────────────────────────────────
    // Data source (single-sheet mode)
    // ──────────────────────────────────────────

    /**
     * Set an Eloquent query as the data source.
     *
     * Uses chunked processing for memory efficiency with large datasets. Pass a
     * factory closure (fn () => User::query()) rather than a live builder when
     * the export will be queued, so the query can be rebuilt inside the job.
     *
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed>|(Closure(): (EloquentBuilder<\Illuminate\Database\Eloquent\Model>|Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed>))  $builder
     */
    public function fromQuery(EloquentBuilder|Relation|Closure $builder): self
    {
        $this->defaultSheet->fromQuery($builder);

        return $this;
    }

    /**
     * Set a Collection as the data source.
     *
     * @param  Collection<int, mixed>  $collection
     */
    public function fromCollection(Collection $collection): self
    {
        $this->defaultSheet->fromCollection($collection);

        return $this;
    }

    /**
     * Set a plain array as the data source.
     *
     * @param  array<int, mixed>  $rows
     */
    public function fromArray(array $rows): self
    {
        $this->defaultSheet->fromArray($rows);

        return $this;
    }

    /**
     * Set a Traversable iterator as the data source (consumed once, lazily).
     *
     * @param  \Traversable<int, mixed>  $iterator
     */
    public function fromIterator(\Traversable $iterator): self
    {
        $this->defaultSheet->fromIterator($iterator);

        return $this;
    }

    /**
     * Set a generator factory as the data source (streamed).
     *
     * @param  Closure(): iterable<int, mixed>  $generator
     */
    public function fromGenerator(Closure $generator): self
    {
        $this->defaultSheet->fromGenerator($generator);

        return $this;
    }

    /**
     * Render a Blade view as the sheet content.
     *
     * @param  \Illuminate\Contracts\View\View|(Closure(): \Illuminate\Contracts\View\View)  $view
     */
    public function fromView(\Illuminate\Contracts\View\View|Closure $view): self
    {
        $this->defaultSheet->fromView($view);

        return $this;
    }

    /**
     * Set headers and mapping from a reusable Exportable class.
     *
     * @param  Exportable  $exportable  Defines headers() and map().
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed>|Collection<int, mixed>  $source  The data source.
     */
    public function fromExportable(Exportable $exportable, EloquentBuilder|Relation|Collection $source): self
    {
        $this->defaultSheet->fromExportable($exportable, $source);

        return $this;
    }

    // ──────────────────────────────────────────
    // Column configuration (single-sheet mode)
    // ──────────────────────────────────────────

    /**
     * Set the column header labels.
     *
     * @param  array<int, string>  $headers
     */
    public function withHeaders(array $headers): self
    {
        $this->defaultSheet->withHeaders($headers);

        return $this;
    }

    /**
     * Set the callback that maps each data row to cell values.
     *
     * The callback receives a single row (model or array) and must return
     * an array of values, one per column.
     */
    public function withMapping(Closure $callback): self
    {
        $this->defaultSheet->withMapping($callback);

        return $this;
    }

    // ──────────────────────────────────────────
    // Sheet configuration (single-sheet mode)
    // ──────────────────────────────────────────

    /**
     * Include a serial number column as the first column.
     */
    public function withSerialNumber(bool $include = true): self
    {
        $this->defaultSheet->withSerialNumber($include);

        return $this;
    }

    /**
     * Override the default styling for this export.
     */
    public function withStyling(StyleConfig $styling): self
    {
        $this->defaultSheet->withStyling($styling);

        return $this;
    }

    /**
     * Override the CSV/TSV writer settings for this export (delimiter, BOM, etc.).
     */
    public function withCsvSettings(CsvSettings $settings): self
    {
        $this->defaultSheet->withCsvSettings($settings);

        return $this;
    }

    /**
     * Set the number of rows processed per chunk (query exports only).
     */
    public function withChunkSize(int $size): self
    {
        $this->defaultSheet->withChunkSize($size);

        return $this;
    }

    /**
     * Limit the total number of rows exported.
     */
    public function withLimit(?int $limit): self
    {
        $this->defaultSheet->withLimit($limit);

        return $this;
    }

    /**
     * Set column number/date formats.
     *
     * @param  array<string, string>  $formats  Keyed by column letter (e.g., ['A' => NumberFormat::FORMAT_DATE_DDMMYYYY]).
     */
    public function withColumnFormats(array $formats): self
    {
        $this->defaultSheet->withColumnFormats($formats);

        return $this;
    }

    /**
     * Set explicit column widths (overrides auto-size for specified columns).
     *
     * @param  array<string, float|int>  $widths  Keyed by column letter (e.g., ['A' => 20, 'B' => 35.5]).
     */
    public function withColumnWidths(array $widths): self
    {
        $this->defaultSheet->withColumnWidths($widths);

        return $this;
    }

    /**
     * Style the rendered worksheet via a callback (equivalent to an after-sheet hook).
     *
     * @param  Closure(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet): void  $callback
     */
    public function styleUsing(Closure $callback): self
    {
        $this->defaultSheet->styleUsing($callback);

        return $this;
    }

    /**
     * Set the top-left cell where the data begins (e.g. "B2").
     */
    public function startCell(string $coordinate): self
    {
        $this->defaultSheet->startCell($coordinate);

        return $this;
    }

    /**
     * Use strict null comparison when writing cell values.
     */
    public function strictNulls(bool $strict = true): self
    {
        $this->defaultSheet->strictNulls($strict);

        return $this;
    }

    /**
     * Set a custom PhpSpreadsheet value binder for cell type inference.
     */
    public function bindValuesUsing(\PhpOffice\PhpSpreadsheet\Cell\IValueBinder $binder): self
    {
        $this->defaultSheet->bindValuesUsing($binder);

        return $this;
    }

    /**
     * Attach charts to the sheet (xlsx only).
     *
     * @param  array<int, \PhpOffice\PhpSpreadsheet\Chart\Chart>  $charts
     */
    public function withCharts(array $charts): self
    {
        $this->defaultSheet->withCharts($charts);

        return $this;
    }

    /**
     * Attach images/drawings to the sheet (xlsx only).
     *
     * @param  array<int, \PhpOffice\PhpSpreadsheet\Worksheet\BaseDrawing>  $drawings
     */
    public function withImages(array $drawings): self
    {
        $this->defaultSheet->withImages($drawings);

        return $this;
    }

    // ──────────────────────────────────────────
    // Multi-sheet mode
    // ──────────────────────────────────────────

    /**
     * Add a named sheet to a multi-sheet workbook.
     *
     * The closure receives a SheetBuilder to configure the sheet's data
     * source, headers, mapping, and styling independently.
     *
     * @param  string  $title  The sheet tab name.
     * @param  Closure(SheetBuilder): void  $configurator  Configures the sheet.
     */
    public function addSheet(string $title, Closure $configurator): self
    {
        $sheet = $this->createSheetBuilder();
        $sheet->withTitle($title);
        $configurator($sheet);
        $this->sheets[] = $sheet;

        return $this;
    }

    // ──────────────────────────────────────────
    // File-level configuration
    // ──────────────────────────────────────────

    /**
     * Set the export file format (xlsx, csv, tsv, ods, html, pdf).
     */
    public function format(ExportFormat $format): self
    {
        $this->format = $format;

        return $this;
    }

    /**
     * Set a custom filename (without extension). A timestamp is appended automatically.
     */
    public function filename(string $filename): self
    {
        $this->filename = $filename;

        return $this;
    }

    /**
     * Set the filesystem disk for storing the export.
     */
    public function disk(string $disk): self
    {
        $this->disk = $disk;

        return $this;
    }

    /**
     * Override the storage directory (relative to the disk root) at runtime.
     */
    public function path(string $path): self
    {
        $this->pathOverride = trim($path, '/');

        return $this;
    }

    /**
     * Override the maximum-rows guard at runtime (null = unlimited for this export).
     */
    public function maxRows(?int $maxRows): self
    {
        $this->maxRowsOverridden = true;
        $this->maxRowsOverride = $maxRows;

        return $this;
    }

    /**
     * Override the signed download-URL lifetime (in days) for this export.
     */
    public function expireAfterDays(int $days): self
    {
        $this->expireDaysOverride = $days;

        return $this;
    }

    /**
     * Set document metadata properties embedded in the file.
     *
     * @param  array<string, string>  $properties  e.g., ['title' => 'Report', 'creator' => 'System']
     */
    public function withProperties(array $properties): self
    {
        $this->documentProperties = array_merge($this->documentProperties, $properties);

        return $this;
    }

    /**
     * Apply a callback to the workbook's default style (all cells).
     *
     * @param  Closure(\PhpOffice\PhpSpreadsheet\Style\Style): void  $callback
     */
    public function defaultStyleUsing(Closure $callback): self
    {
        $this->defaultStyleCallback = $callback;

        return $this;
    }

    /**
     * Set a default background colour for all cells (RGB hex string or a style array).
     *
     * @param  string|array<string, mixed>  $color
     */
    public function background(string|array $color): self
    {
        $this->background = $color;

        return $this;
    }

    /**
     * Pre-calculate all formulas before saving the workbook.
     */
    public function precalculateFormulas(bool $enabled = true): self
    {
        $this->precalculateFormulas = $enabled;

        return $this;
    }

    /**
     * Enable or disable auto-splitting for this export, optionally overriding
     * the rows-per-sheet threshold.
     *
     * Auto-split applies only to single-sheet query exports in formats with a
     * per-sheet row limit (xlsx, ods). When the row count exceeds the
     * threshold, the export is transparently spread across multiple sheets.
     */
    public function autoSplit(?bool $enabled = true, ?int $rowsPerSheet = null): self
    {
        $this->autoSplitOverride = $enabled;
        $this->rowsPerSheetOverride = $rowsPerSheet;

        return $this;
    }

    // ──────────────────────────────────────────
    // Completion hooks (store() and queue())
    // ──────────────────────────────────────────

    /**
     * Run a callback with the ExportResult once the export has finished.
     *
     * On queue(), the callback runs after the file is written — it must be
     * serializable (its captured variables too), as it is queued via the
     * chained completion job.
     *
     * @param  Closure(ExportResult): void  $callback
     */
    public function onCompletion(Closure $callback): self
    {
        $this->completionCallback = $callback;

        return $this;
    }

    /**
     * Send a notification to a notifiable once the export has finished.
     *
     * @param  object  $notifiable  Typically an Eloquent model using the Notifiable trait.
     */
    public function notify(object $notifiable, Notification $notification): self
    {
        $this->notifiable = $notifiable;
        $this->notification = $notification;

        return $this;
    }

    /**
     * Email the finished export to the given recipients.
     *
     * The file is attached when it is at or below the configured size
     * threshold; otherwise a download link is emailed instead.
     *
     * @param  string|array<int, string>  $recipients
     */
    public function notifyMail(string|array $recipients): self
    {
        $this->mailRecipients = is_array($recipients) ? array_values($recipients) : [$recipients];

        return $this;
    }

    /**
     * Override the subject line of the export email at runtime.
     */
    public function mailSubject(string $subject): self
    {
        $this->mailSubjectOverride = $subject;

        return $this;
    }

    /**
     * Override the attach-vs-link size threshold (bytes) for the export email.
     */
    public function mailAttachThreshold(int $bytes): self
    {
        $this->mailAttachThresholdOverride = $bytes;

        return $this;
    }

    /**
     * Toggle dispatching the ExportCompleted event (enabled by default).
     */
    public function withCompletionEvent(bool $enabled = true): self
    {
        $this->fireCompletionEvent = $enabled;

        return $this;
    }

    /**
     * Set the queue connection used by queue() (overrides config).
     */
    public function onConnection(?string $connection): self
    {
        $this->queueConnection = $connection;

        return $this;
    }

    /**
     * Set the queue name used by queue() (overrides config).
     */
    public function onQueue(?string $queue): self
    {
        $this->queueName = $queue;

        return $this;
    }

    // ──────────────────────────────────────────
    // Output methods
    // ──────────────────────────────────────────

    /**
     * Store the export to disk and return an ExportResult with the download URL.
     *
     * @throws FileExportException
     */
    public function store(): ExportResult
    {
        $document = $this->buildExportObject();
        $resolvedFilename = $this->filenameGenerator->generate($this->filename, $this->format);
        $storagePath = $this->resolveStoragePath($resolvedFilename);
        $storageDisk = $this->disk ?? $this->config->disk;

        $stored = $this->engine->store($document, $storageDisk, $storagePath, $this->format);

        if (! $stored) {
            throw FileExportException::storageFailed($resolvedFilename);
        }

        $downloadUrl = $this->urlResolver->resolve($storageDisk, $storagePath, $this->expireDaysOverride);

        $result = new ExportResult(
            filename: $resolvedFilename,
            url: $downloadUrl,
            path: $storagePath,
            disk: $storageDisk,
            format: $this->format->value,
            mimeType: $this->format->mimeType(),
        );

        $completion = $this->completionConfig();
        if ($completion->hasWork()) {
            $this->completionHandler->handle($result, $completion);
        }

        return $result;
    }

    /**
     * Stream the export directly as a browser download.
     *
     * @throws FileExportException
     */
    public function download(): BinaryFileResponse
    {
        $document = $this->buildExportObject();
        $resolvedFilename = $this->filenameGenerator->generate($this->filename, $this->format);

        return $this->engine->download($document, $resolvedFilename, $this->format);
    }

    /**
     * Queue the export for background processing.
     *
     * Returns immediately with the expected filename. The file is written
     * to disk asynchronously by your queue worker.
     *
     * @throws FileExportException
     */
    public function queue(): ExportResult
    {
        $this->assertQueueable();

        $document = $this->buildExportObject();
        $resolvedFilename = $this->filenameGenerator->generate($this->filename, $this->format);
        $storagePath = $this->resolveStoragePath($resolvedFilename);
        $storageDisk = $this->disk ?? $this->config->disk;

        $pending = PerformExport::dispatch(
            $document,
            $storageDisk,
            $storagePath,
            $resolvedFilename,
            $this->format,
            $this->fireCompletionEvent,
            $this->notifiable,
            $this->notification,
            $this->completionCallback !== null ? new SerializableClosure($this->completionCallback) : null,
            $this->mailRecipients,
            $this->mailSubjectOverride,
            $this->mailAttachThresholdOverride,
            $this->expireDaysOverride,
        );

        if ($this->queueConnection !== null) {
            $pending->onConnection($this->queueConnection);
        }

        if ($this->queueName !== null) {
            $pending->onQueue($this->queueName);
        }

        return new ExportResult(
            filename: $resolvedFilename,
            path: $storagePath,
            disk: $storageDisk,
            format: $this->format->value,
            mimeType: $this->format->mimeType(),
        );
    }

    /**
     * Generate the export and return the raw binary content.
     *
     * Useful for attaching to emails, returning in API responses, or
     * piping to other services without touching disk.
     *
     * @throws FileExportException
     */
    public function raw(): string
    {
        return $this->engine->raw($this->buildExportObject(), $this->format);
    }

    // ──────────────────────────────────────────
    // Internal
    // ──────────────────────────────────────────

    /**
     * @throws FileExportException
     */
    private function buildExportObject(): ExportDocument
    {
        if ($this->sheets !== []) {
            $sheets = [];
            foreach ($this->sheets as $sheetBuilder) {
                $sheets[] = $sheetBuilder->build();
            }

            return $this->makeDocument($sheets);
        }

        $sheets = $this->defaultSheet->buildResolved(
            $this->format,
            $this->autoSplitOverride ?? $this->config->autoSplit,
            $this->rowsPerSheetOverride ?? $this->config->rowsPerSheet,
            $this->maxRowsOverridden ? $this->maxRowsOverride : $this->config->maxRows,
        );

        return $this->makeDocument($sheets);
    }

    /**
     * @param  array<int, \Anil\FileExport\Engine\Contracts\ExportSheet>  $sheets
     */
    private function makeDocument(array $sheets): ExportDocument
    {
        return new ExportDocument(
            sheets: $sheets,
            properties: $this->documentProperties,
            defaultStyleCallback: $this->defaultStyleCallback,
            background: $this->background,
            precalculateFormulas: $this->precalculateFormulas,
        );
    }

    /**
     * Resolve the storage path, honouring a runtime path() override.
     */
    private function resolveStoragePath(string $filename): string
    {
        return ($this->pathOverride ?? $this->config->path).'/'.$filename;
    }

    /**
     * Reject queueing exports built from a live (non-serializable) query builder.
     *
     * @throws FileExportException
     */
    private function assertQueueable(): void
    {
        if ($this->sheets === []) {
            if ($this->defaultSheet->usesLiveQueryBuilder()) {
                throw FileExportException::queueRequiresSerializableQuery();
            }

            return;
        }

        foreach ($this->sheets as $sheet) {
            if ($sheet->usesLiveQueryBuilder()) {
                throw FileExportException::queueRequiresSerializableQuery();
            }
        }
    }

    private function completionConfig(): CompletionConfig
    {
        return new CompletionConfig(
            fireEvent: $this->fireCompletionEvent,
            notifiable: $this->notifiable,
            notification: $this->notification,
            callback: $this->completionCallback,
            mailRecipients: $this->mailRecipients,
            mailSubject: $this->mailSubjectOverride,
            mailAttachThreshold: $this->mailAttachThresholdOverride,
        );
    }

    private function createSheetBuilder(): SheetBuilder
    {
        return new SheetBuilder(
            defaultStyling: $this->config->styling,
            defaultChunkSize: $this->config->chunkSize,
            disableAutoSizeOnLargeSheets: $this->config->disableAutoSizeOnLargeSheets,
            disableStylingOnLargeSheets: $this->config->disableStylingOnLargeSheets,
            defaultCsv: $this->config->csv,
        );
    }
}
