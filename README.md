# Laravel File Export

A powerful, fluent file export package for Laravel, built directly on [PhpSpreadsheet](https://phpspreadsheet.readthedocs.io/) with its own in-house export engine — no third-party Laravel-Excel wrapper.

Export anything from a 10-row collection to a **10M+ row database table** — to XLSX, CSV, TSV,
ODS, HTML, or PDF — and deliver it however you need: download it, store it on any disk, queue it
in the background, email it, or grab the raw bytes.

- **SOLID architecture** — every dependency is constructor-injected and swappable.
- **PHPStan level max** — 0 errors.
- **Pest test suite** — fully covered, runs in parallel.
- **Scales** — chunked query reads, auto-splitting across sheets past the 1,048,576-row limit, and CSV streaming for unlimited rows.

---

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Data Sources](#data-sources)
- [Output Modes](#output-modes)
- [Formats](#formats)
- [Column & Sheet Options](#column--sheet-options)
- [Multi-Sheet Workbooks](#multi-sheet-workbooks)
- [Large Datasets (1M – 10M+ rows)](#large-datasets-1m--10m-rows)
- [Email & Completion Hooks](#email--completion-hooks)
- [Queue Targeting](#queue-targeting)
- [Reusable Exportable Classes](#reusable-exportable-classes)
- [Custom Styling](#custom-styling)
- [Swapping Implementations](#swapping-implementations)
- [Conditionals & Macros](#conditionals--macros)
- [Runtime Overrides](#runtime-overrides)
- [Configuration Reference](#configuration-reference)
- [API Reference](#api-reference)
- [Testing](#testing)
- [Static Analysis](#static-analysis)
- [Architecture](#architecture)
- [License](#license)

---

## Requirements

- PHP 8.2+
- Laravel 11, 12, or 13
- PhpOffice/PhpSpreadsheet 1.30+ (installed automatically)
- `mpdf/mpdf` (only for PDF export)

---

## Installation

```bash
composer require anil/file-export
```

Publish the config (optional):

```bash
php artisan vendor:publish --tag=file-export-config
```

Publish the email template (optional, to customize the email body):

```bash
php artisan vendor:publish --tag=file-export-views
```

The service provider is auto-discovered. PDF export additionally requires:

```bash
composer require mpdf/mpdf
```

---

## Quick Start

### 1. Controller Trait

The simplest way — add `CanExport` to a controller:

```php
use Anil\FileExport\Traits\CanExport;

class UserController extends Controller
{
    use CanExport;

    public function exportUsers()
    {
        return $this->export(
            builder: User::query(),
            headers: ['Name', 'Email', 'Created At'],
            mapping: fn (User $user) => [
                $user->name,
                $user->email,
                $user->created_at->format('Y-m-d'),
            ],
        )->toResponse();
    }
}
```

### 2. Facade

```php
use Anil\FileExport\Facades\FileExport;

return FileExport::fromQuery(User::query())
    ->withHeaders(['Name', 'Email'])
    ->withMapping(fn (User $user) => [$user->name, $user->email])
    ->store()
    ->toResponse();
```

### 3. Dependency Injection (recommended)

```php
use Anil\FileExport\Services\FileExporter;

class ReportController extends Controller
{
    public function __construct(
        private readonly FileExporter $exporter,
    ) {}

    public function generate()
    {
        return $this->exporter
            ->fromQuery(Report::query())
            ->withHeaders(['Title', 'Status'])
            ->withMapping(fn (Report $r) => [$r->title, $r->status])
            ->store()
            ->toResponse();
    }
}
```

---

## Data Sources

### From a query (chunked, memory-efficient)

```php
$exporter->fromQuery(User::query())
    ->withHeaders(['Name', 'Email'])
    ->withMapping(fn (User $u) => [$u->name, $u->email]);
```

> **Queueing a query export?** Pass a *factory closure* instead of a live builder — a live
> Eloquent builder holds a database connection that can't be serialized onto a queue. The
> closure is rebuilt fresh inside the job:
>
> ```php
> $exporter->fromQuery(fn () => User::query()); // for ->queue()
> ```

### From a collection (in-memory)

```php
$exporter->fromCollection(collect($data))
    ->withHeaders(['Key', 'Value'])
    ->withMapping(fn (array $item) => [$item['key'], $item['value']]);
```

### From a relation

```php
$exporter->fromQuery($team->members())
    ->withHeaders(['Name'])
    ->withMapping(fn ($m) => [$m->name]);
```

---

## Output Modes

### Store to disk

```php
$result = $exporter->fromQuery(User::query())
    ->withHeaders(['Name', 'Email'])
    ->withMapping(fn ($u) => [$u->name, $u->email])
    ->store();

$result->filename;     // "users_2024-01-15_143022.xlsx"
$result->url;          // download URL (signed for cloud disks)
$result->path;         // "exports/users_2024-01-15_143022.xlsx"
$result->disk;         // "public"
$result->format;       // "xlsx"
$result->mimeType;     // "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
$result->toResponse(); // Illuminate\Http\JsonResponse
$result->toArray();    // ['success' => true, 'filename' => ..., 'url' => ..., 'path' => ..., 'disk' => ..., 'format' => ..., 'mime_type' => ...]
```

### Direct download

```php
return $exporter->fromQuery(User::query())
    ->withHeaders(['Name', 'Email'])
    ->withMapping(fn ($u) => [$u->name, $u->email])
    ->download();
```

### Queue for background processing

```php
$result = $exporter->fromQuery(fn () => User::query())
    ->withHeaders(['Name', 'Email'])
    ->withMapping(fn ($u) => [$u->name, $u->email])
    ->queue();
```

Returns immediately with the expected filename; the file is written by your queue worker.
Mapping callbacks are serialized automatically. See [Email & Completion Hooks](#email--completion-hooks)
to be notified when a queued export finishes.

### Raw binary content

Useful for email attachments, API responses, or piping elsewhere without touching disk:

```php
$binary = $exporter->fromQuery(User::query())
    ->withHeaders(['Name', 'Email'])
    ->withMapping(fn ($u) => [$u->name, $u->email])
    ->raw();
```

---

## Formats

```php
use Anil\FileExport\Enums\ExportFormat;

->format(ExportFormat::XLSX)   // Excel (default)
->format(ExportFormat::CSV)    // CSV          — no row limit, best for huge exports
->format(ExportFormat::TSV)    // Tab-separated — no row limit
->format(ExportFormat::ODS)    // OpenDocument
->format(ExportFormat::HTML)   // HTML table   — no row limit
->format(ExportFormat::PDF)    // PDF (requires mpdf/mpdf)
```

### CSV / TSV settings

Control the delimiter, enclosure, line ending, and UTF-8 BOM for delimited-text exports. The
BOM is the usual fix for spreadsheet apps that otherwise garble accented characters, and a `;`
delimiter is what Excel expects in many European locales:

```php
use Anil\FileExport\DataTransferObjects\CsvSettings;

$exporter->fromQuery(User::query())
    ->withHeaders(['Name', 'Email'])
    ->withMapping(fn ($u) => [$u->name, $u->email])
    ->format(ExportFormat::CSV)
    ->withCsvSettings(new CsvSettings(
        delimiter: ';',
        useBom: true,
    ))
    ->store();
```

Defaults come from the `csv` config section; these settings are ignored by non-delimited formats.

---

## Column & Sheet Options

```php
$exporter->fromQuery(User::query())
    ->withHeaders(['Name', 'Email'])
    ->withMapping(fn ($u) => [$u->name, $u->email])

    ->withSerialNumber()              // prepend a "S.N." column (label is configurable)
    ->withLimit(5000)                 // export only the first N rows
    ->withChunkSize(5000)             // rows processed per chunk (query exports)
    ->filename('monthly-report')      // a timestamp is appended automatically
    ->disk('s3')                      // any filesystem disk

    ->withColumnFormats([             // PhpSpreadsheet number/date formats
        'A' => \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_DATE_DDMMYYYY,
        'B' => \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
    ])
    ->withColumnWidths([              // explicit widths (override auto-size)
        'A' => 15, 'B' => 25, 'C' => 50,
    ])
    ->withProperties([                // document metadata
        'title'       => 'Monthly Report',
        'creator'     => 'Admin System',
        'company'     => 'Acme Corp',
        'description' => 'Exported user data',
    ])
    ->store();
```

---

## Multi-Sheet Workbooks

```php
use Anil\FileExport\Builders\SheetBuilder;

$result = $exporter
    ->addSheet('Active Users', fn (SheetBuilder $sheet) => $sheet
        ->fromQuery(User::where('active', true))
        ->withHeaders(['Name', 'Email'])
        ->withMapping(fn (User $u) => [$u->name, $u->email])
    )
    ->addSheet('Inactive Users', fn (SheetBuilder $sheet) => $sheet
        ->fromQuery(User::where('active', false))
        ->withHeaders(['Name', 'Email', 'Deactivated At'])
        ->withMapping(fn (User $u) => [$u->name, $u->email, $u->deactivated_at])
    )
    ->withProperties(['title' => 'User Report'])
    ->filename('user-report')
    ->store();
```

Each sheet has its own data source, headers, mapping, styling, serial numbers, column widths, and formatting.

---

## Large Datasets (1M – 10M+ rows)

XLSX and ODS are capped at **1,048,576 rows per sheet**. When a single-sheet query export exceeds
the configured threshold, the package transparently **auto-splits** it across multiple sheets
(`Sheet1`, `Sheet2`, …) in one workbook — with serial numbers continuing across sheets:

```php
$exporter->fromQuery(fn () => Order::query())
    ->withHeaders(['ID', 'Total'])
    ->withMapping(fn ($o) => [$o->id, $o->total])
    ->autoSplit(true, 1_000_000)   // optional per-export override of the config threshold
    ->store();
```

Auto-split is enabled by default (configurable). For very large sheets it automatically skips the
expensive auto-column-sizing and full-range styling, which are impractical at that scale.

**For truly huge exports (10M+ rows), prefer CSV** — it streams in a single pass with no row limit
and no splitting:

```php
$exporter->fromQuery(fn () => Order::query())
    ->withHeaders(['ID', 'Total'])
    ->withMapping(fn ($o) => [$o->id, $o->total])
    ->format(ExportFormat::CSV)
    ->queue();
```

### Row guard (`max_rows`)

`max_rows` caps a single physical sheet/file for spreadsheet formats and throws a
`FileExportException` if exceeded — auto-split is the sanctioned way to go beyond it. Set it to
`null` for unlimited CSV dumps.

---

## Email & Completion Hooks

React to a finished export on **both** the synchronous `store()` path and after a queued export's
write jobs complete. Mix and match any of the four mechanisms:

```php
use Anil\FileExport\DataTransferObjects\ExportResult;

$exporter->fromQuery(fn () => User::query())
    ->withHeaders(['Name', 'Email'])
    ->withMapping(fn ($u) => [$u->name, $u->email])

    // 1. Email the file (auto: attach if small, link if large — see mail config)
    ->notifyMail('boss@example.com')              // or ['a@x.com', 'b@x.com']

    // 2. Send a Laravel Notification to a notifiable
    ->notify($user, new ExportReadyNotification)

    // 3. Run a callback with the ExportResult
    ->onCompletion(fn (ExportResult $result) => Log::info($result->url))

    // 4. The ExportCompleted event always fires (disable with ->withCompletionEvent(false))
    ->queue();
```

Listen for the event anywhere:

```php
use Anil\FileExport\Events\ExportCompleted;

Event::listen(ExportCompleted::class, function (ExportCompleted $e) {
    // $e->result->filename, $e->result->url, $e->result->path, $e->result->disk
});
```

### Email delivery

Emailed files are **attached** when at or below `mail.attach_threshold` bytes (10 MB by default),
otherwise a **download link** is sent instead (signed for cloud disks). The email body is a
responsive, dark-mode-aware HTML template with a bulletproof download button — customize it by
publishing the view:

```bash
php artisan vendor:publish --tag=file-export-views
# edit resources/views/vendor/file-export/emails/export-ready.blade.php
```

> When queueing, the completion callback must be serializable — its captured variables too
> (a closure that captures a PDO connection or open resource cannot be queued).

---

## Queue Targeting

Choose the connection and queue for background exports (defaults come from the `queue` config section):

```php
$exporter->fromQuery(fn () => User::query())
    ->withHeaders(['Name'])
    ->withMapping(fn ($u) => [$u->name])
    ->onConnection('redis')
    ->onQueue('exports')
    ->queue();
```

---

## Reusable Exportable Classes

Create self-contained, reusable export definitions:

```php
use Anil\FileExport\Contracts\Exportable;

class UserExport implements Exportable
{
    public function headers(): array
    {
        return ['Name', 'Email', 'Role'];
    }

    public function map(mixed $row): array
    {
        return [$row->name, $row->email, $row->role->name];
    }
}

// Usage:
$exporter->fromExportable(new UserExport, User::with('role')->query())->store();
```

---

## Custom Styling

Override the default styling from config for a specific export:

```php
use Anil\FileExport\DataTransferObjects\StyleConfig;

$style = new StyleConfig(
    headerBold: true,
    headerFontSize: 14,
    headerFontColor: 'FFFFFF',
    headerBackground: 'FF0000',
    wrapText: false,
    freezeHeader: true,
    autoFilter: false,
    serialNumberHeader: '#',
);

$exporter->fromQuery(User::query())
    ->withHeaders(['Name', 'Email'])
    ->withMapping(fn (User $u) => [$u->name, $u->email])
    ->withStyling($style)
    ->store();
```

---

## Swapping Implementations

The filename and URL strategies are contracts — bind your own in a service provider
(Dependency Inversion):

```php
use Anil\FileExport\Contracts\FilenameGenerator;
use Anil\FileExport\Contracts\UrlResolver;

$this->app->bind(FilenameGenerator::class, MyHashedFilenameGenerator::class);
$this->app->bind(UrlResolver::class, CloudfrontUrlResolver::class);
```

`FilenameGenerator::generate(?string $customName, ExportFormat $format): string` and
`UrlResolver::resolve(string $disk, string $path): string` are the only methods to implement.

---

## Conditionals & Macros

The exporter is `Conditionable` and `Macroable`. Use `when()` / `unless()` to build exports
fluently without breaking the chain:

```php
$exporter->fromQuery(User::query())
    ->withHeaders(['Name'])
    ->withMapping(fn ($u) => [$u->name])
    ->when($request->boolean('with_index'), fn ($e) => $e->withSerialNumber())
    ->when($request->filled('format'), fn ($e) => $e->format(ExportFormat::from($request->format)))
    ->store();
```

Add your own reusable shortcuts with a macro (e.g. in a service provider):

```php
use Anil\FileExport\Services\FileExporter;

FileExporter::macro('asReport', function (string $name) {
    /** @var FileExporter $this */
    return $this->filename($name)->withSerialNumber()->withCompletionEvent();
});

$exporter->fromQuery(User::query())->withHeaders([...])->withMapping(...)->asReport('q3')->store();
```

---

## Runtime Overrides

Everything in `config/file-export.php` is a *default* — every value can be overridden from
code, per export, without touching the config file. This is the idiomatic way to vary behavior
at runtime (per user, per request, per report type):

```php
use Anil\FileExport\DataTransferObjects\StyleConfig;
use Anil\FileExport\DataTransferObjects\CsvSettings;

$exporter->fromQuery(User::query())
    ->withHeaders(['Name'])
    ->withMapping(fn ($u) => [$u->name])

    ->disk('s3')                       // config: disk
    ->path("reports/{$tenant->id}")    // config: path
    ->format(ExportFormat::CSV)        // config: default_format
    ->withChunkSize(2000)              // config: chunk_size
    ->maxRows(null)                    // config: max_rows (null = unlimited here)
    ->autoSplit(true, 500_000)         // config: auto_split.*
    ->expireAfterDays(30)              // config: expire_days (signed-URL lifetime)
    ->withStyling($style)              // config: styling.*
    ->withCsvSettings($csv)            // config: csv.*
    ->withProperties([...])            // config: properties.*
    ->onConnection('redis')->onQueue('exports') // config: queue.*
    ->notifyMail('ops@acme.test')
    ->mailSubject('Tenant export')     // config: mail.subject
    ->mailAttachThreshold(5_000_000)   // config: mail.attach_threshold
    ->store();
```

| Override | Replaces config key |
| --- | --- |
| `disk(string)` | `disk` |
| `path(string)` | `path` |
| `format(ExportFormat)` | `default_format` |
| `withChunkSize(int)` | `chunk_size` |
| `maxRows(?int)` | `max_rows` |
| `autoSplit(?bool, ?int)` | `auto_split.enabled`, `auto_split.rows_per_sheet` |
| `expireAfterDays(int)` | `expire_days` |
| `withStyling(StyleConfig)` | `styling.*` |
| `withCsvSettings(CsvSettings)` | `csv.*` |
| `withProperties(array)` | `properties.*` |
| `onConnection(?string)` / `onQueue(?string)` | `queue.connection` / `queue.queue` |
| `mailSubject(string)` | `mail.subject` |
| `mailAttachThreshold(int)` | `mail.attach_threshold` |

> Need new **defaults** for every export in a request (not per-export)? Rebind the
> `ExportConfig` singleton in a service provider — it's the single source of truth that all
> exporters resolve from.

---

## Configuration Reference

```php
// config/file-export.php
return [
    // Storage disk for stored exports (must exist in config/filesystems.php).
    'disk'           => env('FILE_EXPORT_DISK', 'public'),

    // Directory (relative to the disk root) where files are written.
    'path'           => env('FILE_EXPORT_PATH', 'exports'),

    // Signed-URL expiration in days (cloud disks only).
    'expire_days'    => (int) env('FILE_EXPORT_EXPIRE_DAYS', 7),

    // Default format when none is specified: xlsx, csv, tsv, ods, html, pdf.
    'default_format' => env('FILE_EXPORT_FORMAT', 'xlsx'),

    // Max rows per sheet/file for spreadsheet formats. null = unlimited.
    'max_rows'       => (int) env('FILE_EXPORT_MAX_ROWS', 1000000),

    // Rows processed per chunk for query exports.
    'chunk_size'     => (int) env('FILE_EXPORT_CHUNK_SIZE', 1000),

    // Queue connection/name for ->queue(). null = application defaults.
    'queue' => [
        'connection' => env('FILE_EXPORT_QUEUE_CONNECTION'),
        'queue'      => env('FILE_EXPORT_QUEUE'),
    ],

    // Auto-split oversized xlsx/ods query exports across multiple sheets.
    'auto_split' => [
        'enabled'                          => (bool) env('FILE_EXPORT_AUTO_SPLIT', true),
        'rows_per_sheet'                   => (int) env('FILE_EXPORT_ROWS_PER_SHEET', 1000000),
        'disable_autosize'                 => (bool) env('FILE_EXPORT_LARGE_DISABLE_AUTOSIZE', true),
        'disable_styling_on_large_sheets'  => (bool) env('FILE_EXPORT_LARGE_DISABLE_STYLING', true),
    ],

    // CSV/TSV writer settings (ignored by xlsx/ods/etc.).
    'csv' => [
        'delimiter'              => env('FILE_EXPORT_CSV_DELIMITER', ','),
        'enclosure'              => env('FILE_EXPORT_CSV_ENCLOSURE', '"'),
        'line_ending'            => env('FILE_EXPORT_CSV_LINE_ENDING', "\n"),
        'use_bom'                => (bool) env('FILE_EXPORT_CSV_USE_BOM', false),
        'include_separator_line' => (bool) env('FILE_EXPORT_CSV_SEPARATOR_LINE', false),
        'excel_compatibility'    => (bool) env('FILE_EXPORT_CSV_EXCEL_COMPAT', false),
        'output_encoding'        => env('FILE_EXPORT_CSV_OUTPUT_ENCODING', ''),
    ],

    // Email delivery: attach if <= threshold bytes, else send a download link.
    'mail' => [
        'attach_threshold' => (int) env('FILE_EXPORT_MAIL_ATTACH_THRESHOLD', 10 * 1024 * 1024),
        'subject'          => env('FILE_EXPORT_MAIL_SUBJECT', 'Your export is ready'),
    ],

    // Auto-generated filename pattern: {prefix}-{random}_{timestamp}.{ext}
    'filename' => [
        'prefix'           => env('FILE_EXPORT_FILENAME_PREFIX', 'export'),
        'timestamp_format' => env('FILE_EXPORT_TIMESTAMP_FORMAT', 'Y-m-d_His'),
        'random_length'    => (int) env('FILE_EXPORT_RANDOM_LENGTH', 8),
    ],

    // Default document metadata (overridable per-export via withProperties()).
    'properties' => [
        'creator' => env('FILE_EXPORT_CREATOR', ''),
        'company' => env('FILE_EXPORT_COMPANY', ''),
    ],

    // Default styling (overridable per-export via withStyling()).
    // Colors are ARGB hex (e.g. "4472C4"). Empty string disables.
    'styling' => [
        'header_bold'           => true,
        'header_font_size'      => 12,
        'header_font_color'     => 'FFFFFF',
        'header_background'     => '4472C4',
        'wrap_text'             => true,
        'freeze_header'         => true,
        'auto_filter'           => true,
        'serial_number_header'  => 'S.N.',
    ],
];
```

Every value supports an `env()` override (see the keys above).

---

## API Reference

### Data source
| Method | Description |
| --- | --- |
| `fromQuery(Builder\|Relation\|Closure $source)` | Eloquent query (or factory closure for queued exports). |
| `fromCollection(Collection $data)` | In-memory collection. |
| `fromExportable(Exportable $e, $source)` | Headers + mapping from a reusable class. |

### Columns & sheet
| Method | Description |
| --- | --- |
| `withHeaders(array $headers)` | Column header labels. |
| `withMapping(Closure $cb)` | Maps each row to an array of cell values. |
| `withSerialNumber(bool = true)` | Prepend a serial-number column. |
| `withLimit(?int $limit)` | Cap the total exported rows. |
| `withChunkSize(int $size)` | Rows per chunk (query exports). |
| `withColumnFormats(array $formats)` | Per-column number/date formats. |
| `withColumnWidths(array $widths)` | Explicit column widths. |
| `withStyling(StyleConfig $style)` | Override default styling. |
| `withCsvSettings(CsvSettings $s)` | Delimiter / enclosure / BOM for CSV & TSV. |
| `withProperties(array $props)` | Document metadata. |

### File & format
| Method | Description |
| --- | --- |
| `format(ExportFormat $format)` | Output format. |
| `filename(string $name)` | Custom filename (timestamp appended). |
| `disk(string $disk)` | Target filesystem disk. |
| `path(string $path)` | Override the storage directory. |
| `maxRows(?int $maxRows)` | Override the row guard (null = unlimited). |
| `expireAfterDays(int $days)` | Override the signed-URL lifetime. |
| `autoSplit(?bool $enabled = true, ?int $rowsPerSheet = null)` | Per-export auto-split control. |
| `addSheet(string $title, Closure $cfg)` | Add a sheet to a multi-sheet workbook. |

### Completion & queue
| Method | Description |
| --- | --- |
| `notifyMail(string\|array $recipients)` | Email the export (attach or link). |
| `mailSubject(string $subject)` | Override the email subject. |
| `mailAttachThreshold(int $bytes)` | Override the attach-vs-link size threshold. |
| `notify(object $notifiable, Notification $n)` | Send a Notification on completion. |
| `onCompletion(Closure $cb)` | Run a callback with the `ExportResult`. |
| `withCompletionEvent(bool = true)` | Toggle the `ExportCompleted` event. |
| `onConnection(?string $connection)` | Queue connection for `queue()`. |
| `onQueue(?string $queue)` | Queue name for `queue()`. |

### Output
| Method | Returns | Description |
| --- | --- | --- |
| `store()` | `ExportResult` | Write to disk + resolve URL. |
| `download()` | `BinaryFileResponse` | Stream as a browser download. |
| `queue()` | `ExportResult` | Dispatch to the queue. |
| `raw()` | `string` | Raw file bytes. |

### Conditionals & macros
`when($value, $callback)`, `unless($value, $callback)` (via `Conditionable`), and
`FileExporter::macro($name, $callback)` (via `Macroable`).

### `CanExport` trait helpers
`export($builder, $headers, $mapping)`, `exportCollection($collection, $headers, $mapping)`,
`exportUsing(Exportable $e, $source)`, and `fileExporter()` for full access.

---

## Testing

The package's own suite is written in [Pest](https://pestphp.com/) on top of `orchestra/testbench`:

```bash
composer test            # vendor/bin/pest
vendor/bin/pest --parallel
```

Exports render to a real disk, so tests assert against `Storage::fake()`:

```php
use Anil\FileExport\Services\FileExporter;
use Illuminate\Support\Facades\Storage;

it('exports users', function () {
    Storage::fake('local');

    app(FileExporter::class)
        ->fromQuery(User::query())
        ->withHeaders(['Name'])
        ->withMapping(fn ($u) => [$u->name])
        ->store();

    Storage::disk('local')->assertExists('exports/users.xlsx');
});
```

Queued exports dispatch a `PerformExport` job — assert with `Queue::fake()`:

```php
use Anil\FileExport\Jobs\PerformExport;
use Illuminate\Support\Facades\Queue;

Queue::fake();
// ... ->queue();
Queue::assertPushed(PerformExport::class);
```

Every dependency is constructor-injected, so the rendering engine is fully swappable/mockable
via the `Anil\FileExport\Engine\Contracts\ExportEngine` contract:

```php
use Anil\FileExport\Engine\Contracts\ExportEngine;

$engine = Mockery::mock(ExportEngine::class);
$engine->shouldReceive('store')->once()->andReturn(true);

$exporter = new FileExporter($config, $filenameGenerator, $urlResolver, $engine, $completionHandler);
```

---

## Static Analysis

```bash
composer analyse
```

PHPStan level max with Larastan — 0 errors.

---

## Architecture

```
src/
├── Builders/
│   └── SheetBuilder.php                   # Configures a single sheet (+ auto-split, max_rows)
├── Contracts/
│   ├── Exportable.php                     # User export definition contract
│   ├── FilenameGenerator.php              # Swappable filename strategy
│   └── UrlResolver.php                    # Swappable URL resolution
├── DataTransferObjects/
│   ├── CompletionConfig.php               # Completion options (event/notify/callback/email)
│   ├── CsvSettings.php                    # CSV/TSV writer settings (delimiter/BOM/…)
│   ├── ExportConfig.php                   # Full config as a typed readonly DTO
│   ├── ExportResult.php                   # Result from store()/queue() (filename/url/format/mime/…)
│   └── StyleConfig.php                    # Styling config readonly DTO
├── Engine/
│   ├── Contracts/
│   │   ├── ExportEngine.php               # Rendering-engine contract (store/download/raw)
│   │   └── ExportSheet.php                # A renderable sheet (headings + streaming rows + style)
│   ├── ExportDocument.php                 # Sheets + document properties (engine input)
│   ├── SheetStyler.php                    # Applies PhpSpreadsheet styling to a worksheet
│   ├── SpreadsheetEngine.php              # Default engine (PhpSpreadsheet + temp files)
│   └── StreamingCsvWriter.php             # O(1)-memory CSV/TSV streaming writer
├── Enums/
│   └── ExportFormat.php                   # XLSX, CSV, TSV, ODS, HTML, PDF (+ makeWriter/PDF driver)
├── Events/
│   └── ExportCompleted.php                # Fired when an export finishes
├── Exceptions/
│   └── FileExportException.php            # Named constructors for each error
├── Exports/
│   ├── Concerns/
│   │   └── HasSheetFormatting.php         # Shared sheet metadata (zero duplication)
│   ├── BaseQueryExport.php                # Shared streaming-query sheet (+ serialization)
│   ├── CollectionExport.php               # In-memory collection sheet
│   ├── LargeQueryExport.php               # Query sheet without auto-sizing (split/large)
│   └── QueryExport.php                    # Auto-sized query sheet
├── Facades/
│   └── FileExport.php                     # @mixin FileExporter
├── Jobs/
│   └── PerformExport.php                  # Queued render + store + completion (one job)
├── Mail/
│   └── ExportReadyMail.php                # Attach-or-link email of the export
├── Providers/
│   └── FileExportServiceProvider.php      # Container bindings + views
├── Services/
│   ├── ExportCompletionHandler.php        # Runs completion actions (sync + queued)
│   ├── FileExporter.php                   # Main fluent API
│   ├── TemporaryUrlResolver.php           # Try signed URL, fallback to public
│   └── TimestampedFilenameGenerator.php   # prefix-random_timestamp.ext
└── Traits/
    └── CanExport.php                      # Controller convenience methods

resources/views/emails/export-ready.blade.php   # Publishable, responsive email body
```

---

## License

The MIT License (MIT). See [LICENSE](LICENSE) for details.
