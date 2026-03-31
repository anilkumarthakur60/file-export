# Laravel File Export

A powerful, fluent file export package for Laravel built on top of [Maatwebsite/Excel](https://laravel-excel.com/).

SOLID architecture. PHPStan level max. Fully injectable and mockable — every dependency comes through the constructor.

## Requirements

- PHP 8.2+
- Laravel 11, 12, or 13
- Maatwebsite/Excel 3.1+

## Installation

```bash
composer require anil/file-export
```

Publish the config:

```bash
php artisan vendor:publish --tag=file-export-config
```

---

## Quick Start

### Controller Trait

The simplest way — add `CanExport` to your controller:

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

### Facade

```php
use Anil\FileExport\Facades\FileExport;

return FileExport::fromQuery(User::query())
    ->withHeaders(['Name', 'Email'])
    ->withMapping(fn (User $user) => [$user->name, $user->email])
    ->store()
    ->toResponse();
```

### Dependency Injection (Recommended for Testing)

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

## Formats

```php
use Anil\FileExport\Enums\ExportFormat;

->format(ExportFormat::XLSX)   // Excel (default)
->format(ExportFormat::CSV)    // CSV
->format(ExportFormat::TSV)    // Tab-separated
->format(ExportFormat::ODS)    // OpenDocument
->format(ExportFormat::HTML)   // HTML table
->format(ExportFormat::PDF)    // PDF (requires mpdf/mpdf)
```

---

## Output Modes

### Store to disk

```php
$result = $exporter->fromQuery(User::query())
    ->withHeaders(['Name', 'Email'])
    ->withMapping(fn ($u) => [$u->name, $u->email])
    ->store();

$result->filename;  // "users_2024-01-15_143022.xlsx"
$result->url;       // Download URL (signed for cloud disks)
$result->path;      // "exports/users_2024-01-15_143022.xlsx"
$result->disk;      // "public"
$result->toResponse();  // JsonResponse
$result->toArray();     // Plain array
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
$result = $exporter->fromQuery(User::query())
    ->withHeaders(['Name', 'Email'])
    ->withMapping(fn ($u) => [$u->name, $u->email])
    ->queue();
```

### Raw binary content

Useful for email attachments, API responses, or piping to other services:

```php
$binary = $exporter->fromQuery(User::query())
    ->withHeaders(['Name', 'Email'])
    ->withMapping(fn ($u) => [$u->name, $u->email])
    ->raw();
```

---

## Features

### From a Collection

```php
$exporter->fromCollection(collect($data))
    ->withHeaders(['Key', 'Value'])
    ->withMapping(fn (array $item) => [$item['key'], $item['value']])
    ->store();
```

### Serial Numbers

```php
->withSerialNumber()  // Adds "S.N." column (configurable label)
```

### Row Limit

```php
->withLimit(5000)  // Export only first 5000 rows
```

### Chunk Size

```php
->withChunkSize(5000)  // Process 5000 rows at a time (query exports)
```

### Custom Filename and Disk

```php
->filename('monthly-report')  // Timestamp appended automatically
->disk('s3')
```

### Column Formatting

```php
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

->withColumnFormats([
    'A' => NumberFormat::FORMAT_DATE_DDMMYYYY,
    'B' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
])
```

### Column Widths

```php
->withColumnWidths([
    'A' => 15,
    'B' => 25,
    'C' => 50,
])
```

### Document Properties

```php
->withProperties([
    'title' => 'Monthly Report',
    'creator' => 'Admin System',
    'company' => 'Acme Corp',
    'description' => 'Exported user data',
])
```

---

## Multi-Sheet Exports

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

## Exportable Contract

Create reusable export definitions:

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

Override the filename or URL strategy in your `AppServiceProvider`:

```php
use Anil\FileExport\Contracts\FilenameGenerator;
use Anil\FileExport\Contracts\UrlResolver;

$this->app->bind(FilenameGenerator::class, MyHashedFilenameGenerator::class);
$this->app->bind(UrlResolver::class, CloudfrontUrlResolver::class);
```

---

## Testing

Every dependency is injected via constructor — no hidden static calls in service classes.

```php
use Anil\FileExport\Services\FileExporter;
use Maatwebsite\Excel\Excel;
use Mockery;

// Mock the Excel service
$excel = Mockery::mock(Excel::class);
$excel->shouldReceive('store')->once()->andReturn(true);

// Mock the filename generator
$filenameGenerator = Mockery::mock(FilenameGenerator::class);
$filenameGenerator->shouldReceive('generate')->andReturn('test_2024-01-01_120000.xlsx');

// Mock the URL resolver
$urlResolver = Mockery::mock(UrlResolver::class);
$urlResolver->shouldReceive('resolve')->andReturn('https://example.com/exports/test.xlsx');

// Create a fully testable exporter
$exporter = new FileExporter($config, $filenameGenerator, $urlResolver, $excel);

$result = $exporter->fromCollection(collect([...]))
    ->withHeaders(['Name'])
    ->withMapping(fn ($row) => [$row['name']])
    ->store();

$this->assertEquals('test_2024-01-01_120000.xlsx', $result->filename);
```

---

## Configuration

```php
// config/file-export.php
return [
    'disk'           => 'public',
    'path'           => 'exports',
    'expire_days'    => 7,
    'default_format' => 'xlsx',
    'max_rows'       => 1000000,
    'chunk_size'     => 1000,

    'filename' => [
        'prefix'           => 'export',
        'timestamp_format' => 'Y-m-d_His',
        'random_length'    => 8,
    ],

    'properties' => [
        'creator' => '',
        'company' => '',
    ],

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

All values support `env()` overrides. See the config file for environment variable names.

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
│   └── SheetBuilder.php                   # Configures a single sheet
├── Contracts/
│   ├── Exportable.php                     # User export definition contract
│   ├── FilenameGenerator.php              # Swappable filename strategy
│   └── UrlResolver.php                    # Swappable URL resolution
├── DataTransferObjects/
│   ├── ExportConfig.php                   # Full config as typed readonly DTO
│   ├── ExportResult.php                   # Result from store()/queue()
│   └── StyleConfig.php                    # Styling config readonly DTO
├── Enums/
│   └── ExportFormat.php                   # XLSX, CSV, TSV, ODS, HTML, PDF
├── Exceptions/
│   └── FileExportException.php            # Named constructors for each error
├── Exports/
│   ├── Concerns/
│   │   └── HasSheetFormatting.php         # Shared formatting (zero duplication)
│   ├── CollectionExport.php               # FromCollection
│   ├── MultiSheetExport.php               # WithMultipleSheets wrapper
│   └── QueryExport.php                    # FromQuery + chunked
├── Facades/
│   └── FileExport.php                     # @mixin FileExporter
├── Providers/
│   └── FileExportServiceProvider.php      # Container bindings
├── Services/
│   ├── FileExporter.php                   # Main fluent API
│   ├── TemporaryUrlResolver.php           # Try signed URL, fallback to public
│   └── TimestampedFilenameGenerator.php   # prefix-random_timestamp.ext
└── Traits/
    └── CanExport.php                      # Controller convenience methods
```

## License

MIT
