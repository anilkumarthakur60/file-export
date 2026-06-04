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
use Anil\FileExport\Enums\ExportFormat;
use Anil\FileExport\Exceptions\FileExportException;
use Anil\FileExport\Exports\BaseQueryExport;
use Anil\FileExport\Exports\CollectionExport;
use Anil\FileExport\Exports\MultiSheetExport;
use Anil\FileExport\Jobs\HandleExportCompletion;
use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use Laravel\SerializableClosure\SerializableClosure;
use Maatwebsite\Excel\Exporter;
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

    /** @var array<string, string> */
    private array $documentProperties = [];

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
     * @param  Exporter  $excel  The Maatwebsite Excel service (injected via interface, not facade).
     * @param  ExportCompletionHandler  $completionHandler  Runs post-export actions (event/notify/callback/email).
     */
    public function __construct(
        private readonly ExportConfig $config,
        private readonly FilenameGenerator $filenameGenerator,
        private readonly UrlResolver $urlResolver,
        private readonly Exporter $excel,
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
        $exportObject = $this->buildExportObject();
        $resolvedFilename = $this->filenameGenerator->generate($this->filename, $this->format);
        $storagePath = $this->config->path.'/'.$resolvedFilename;
        $storageDisk = $this->disk ?? $this->config->disk;

        $stored = $this->excel->store($exportObject, $storagePath, $storageDisk, $this->format->writerType());

        if (! $stored) {
            throw FileExportException::storageFailed($resolvedFilename);
        }

        $downloadUrl = $this->urlResolver->resolve($storageDisk, $storagePath);

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
        $exportObject = $this->buildExportObject();
        $resolvedFilename = $this->filenameGenerator->generate($this->filename, $this->format);

        /** @var BinaryFileResponse */
        return $this->excel->download($exportObject, $resolvedFilename, $this->format->writerType());
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

        $exportObject = $this->buildExportObject();
        $resolvedFilename = $this->filenameGenerator->generate($this->filename, $this->format);
        $storagePath = $this->config->path.'/'.$resolvedFilename;
        $storageDisk = $this->disk ?? $this->config->disk;

        $pending = $this->excel->queue($exportObject, $storagePath, $storageDisk, $this->format->writerType());

        if ($pending instanceof PendingDispatch) {
            if ($this->queueConnection !== null) {
                $pending->onConnection($this->queueConnection);
            }

            if ($this->queueName !== null) {
                $pending->onQueue($this->queueName);
            }

            $completion = $this->completionConfig();
            if ($completion->hasWork()) {
                // Append the completion job AFTER Maatwebsite's write chain so it
                // runs only once the file is fully stored.
                $job = $pending->getJob();
                if (is_object($job) && method_exists($job, 'appendToChain')) {
                    $job->appendToChain($this->makeCompletionJob($resolvedFilename, $storagePath, $storageDisk));
                }
            }
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
        $exportObject = $this->buildExportObject();

        return $this->excel->raw($exportObject, $this->format->writerType());
    }

    // ──────────────────────────────────────────
    // Internal
    // ──────────────────────────────────────────

    /**
     * @throws FileExportException
     */
    private function buildExportObject(): CollectionExport|BaseQueryExport|MultiSheetExport
    {
        if ($this->sheets !== []) {
            return $this->buildMultiSheetExport();
        }

        $this->defaultSheet->withProperties($this->documentProperties);

        return $this->defaultSheet->buildResolved(
            $this->format,
            $this->autoSplitOverride ?? $this->config->autoSplit,
            $this->rowsPerSheetOverride ?? $this->config->rowsPerSheet,
        );
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
        );
    }

    private function makeCompletionJob(string $filename, string $path, string $disk): HandleExportCompletion
    {
        return new HandleExportCompletion(
            disk: $disk,
            path: $path,
            filename: $filename,
            fireEvent: $this->fireCompletionEvent,
            notifiable: $this->notifiable,
            notification: $this->notification,
            callback: $this->completionCallback !== null ? new SerializableClosure($this->completionCallback) : null,
            mailRecipients: $this->mailRecipients,
        );
    }

    /**
     * @throws FileExportException
     */
    private function buildMultiSheetExport(): MultiSheetExport
    {
        $builtSheets = [];

        foreach ($this->sheets as $sheetBuilder) {
            $builtSheets[] = $sheetBuilder->build();
        }

        return new MultiSheetExport(
            sheets: $builtSheets,
            documentProperties: $this->documentProperties,
        );
    }

    private function createSheetBuilder(): SheetBuilder
    {
        return new SheetBuilder(
            defaultStyling: $this->config->styling,
            defaultChunkSize: $this->config->chunkSize,
            maxRows: $this->config->maxRows,
            disableAutoSizeOnLargeSheets: $this->config->disableAutoSizeOnLargeSheets,
            disableStylingOnLargeSheets: $this->config->disableStylingOnLargeSheets,
            defaultCsv: $this->config->csv,
        );
    }
}
