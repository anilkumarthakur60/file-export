# Laravel File Export

A powerful, fluent file export package for Laravel built on top of [Maatwebsite/Excel](https://laravel-excel.com/). Designed following SOLID principles — easy for beginners, extensible for advanced use cases.

## Requirements

- PHP 8.2+
- Laravel 10, 11, 12, or 13
- Maatwebsite/Excel 3.1+

## Installation

```bash
composer require anil/file-export
```

Publish the config (optional):

```bash
php artisan vendor:publish --tag=file-export-config
```

## Quick Start (Beginner)

### Using the HasExport Trait

The simplest way — add the trait to your controller:

```php
use Anil\FileExport\Traits\HasExport;

class UserController extends Controller
{
    use HasExport;

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
        );
    }
}
```

This stores the file to disk and returns a JSON response with the download URL.

### Using the Facade

```php
use Anil\FileExport\Facades\FileExport;

return FileExport::fromQuery(User::query())
    ->withHeaders(['Name', 'Email'])
    ->withMapping(fn (User $user) => [$user->name, $user->email])
    ->store();
```

## Intermediate Usage

### Export Formats

```php
use Anil\FileExport\Enums\ExportFormat;

// CSV
return FileExport::fromQuery(User::query())
    ->withHeaders(['Name', 'Email'])
    ->withMapping(fn (User $user) => [$user->name, $user->email])
    ->format(ExportFormat::CSV)
    ->store();

// PDF (requires mpdf/mpdf)
->format(ExportFormat::PDF)

// Direct download instead of storing
->download();

// Queue for background processing
->queue();
```

### From a Collection

```php
return FileExport::fromCollection(collect($data))
    ->withHeaders(['Key', 'Value'])
    ->withMapping(fn (array $item) => [$item['key'], $item['value']])
    ->store();
```

### Serial Numbers, Limits, Custom Filename

```php
return FileExport::fromQuery(Order::where('status', 'completed'))
    ->withHeaders(['Order #', 'Customer', 'Total'])
    ->withMapping(fn (Order $o) => [$o->number, $o->customer->name, $o->total])
    ->withSerialNumber()       // Adds S.N. column
    ->withLimit(5000)          // Export only first 5000 rows
    ->filename('completed-orders')
    ->disk('s3')
    ->store();
```

### Column Formatting

```php
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

return FileExport::fromQuery(Invoice::query())
    ->withHeaders(['Date', 'Amount'])
    ->withMapping(fn (Invoice $i) => [$i->date, $i->amount])
    ->withColumnFormats([
        'A' => NumberFormat::FORMAT_DATE_DDMMYYYY,
        'B' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
    ])
    ->store();
```

## Advanced Usage (Senior)

### Using the Exportable Contract

Create a dedicated export class for reusability:

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
return FileExport::fromExportable(new UserExport, User::with('role')->query())->store();
```

### Custom Styling per Export

Override the global styling config for a specific export:

```php
use Anil\FileExport\DataTransferObjects\StyleConfig;

$style = new StyleConfig(
    headerBold: true,
    headerBackground: 'FF0000',
    headerFontColor: 'FFFFFF',
    wrapText: false,
    freezeHeader: true,
    autoFilter: false,
    serialNumberHeader: '#',
);

return FileExport::fromQuery(User::query())
    ->withHeaders(['Name', 'Email'])
    ->withMapping(fn (User $u) => [$u->name, $u->email])
    ->withStyleConfig($style)
    ->store();
```

### Chunked Query Exports

For large datasets, query exports are automatically chunked. Control the chunk size:

```php
return FileExport::fromQuery(Log::query())
    ->withHeaders(['Message', 'Level'])
    ->withMapping(fn (Log $l) => [$l->message, $l->level])
    ->withChunkSize(5000)
    ->store();
```

### Injecting ExportService Directly (DI)

For full testability, inject `ExportService` instead of using the facade:

```php
use Anil\FileExport\Services\ExportService;

class ReportController extends Controller
{
    public function __construct(
        private readonly ExportService $exportService,
    ) {}

    public function generate()
    {
        return $this->exportService
            ->fromQuery(Report::query())
            ->withHeaders(['Title', 'Status'])
            ->withMapping(fn (Report $r) => [$r->title, $r->status])
            ->store();
    }
}
```

### Swapping Implementations (OCP / DIP)

The package binds contracts for filename generation and URL resolution. Override them in your `AppServiceProvider`:

```php
use Anil\FileExport\Contracts\FilenameGeneratorContract;
use Anil\FileExport\Contracts\UrlResolverContract;

// Custom filename strategy
$this->app->bind(FilenameGeneratorContract::class, MyCustomFilenameGenerator::class);

// Custom URL resolver (e.g., CDN URLs)
$this->app->bind(UrlResolverContract::class, CdnUrlResolver::class);
```

## Configuration

```php
// config/file-export.php
return [
    'disk'             => 'public',         // Storage disk
    'path'             => 'exports',        // Directory prefix
    'expire_days'      => 7,                // Signed URL expiry (cloud disks)
    'default_format'   => 'xlsx',           // Default format: xlsx, csv, pdf
    'max_rows'         => 1000000,          // Max rows (null = unlimited)
    'chunk_size'       => 1000,             // Rows per chunk (query exports)
    'timestamp_format' => 'Y-m-d_His',     // Filename timestamp format

    'styling' => [
        'header_bold'          => true,
        'header_background'    => '4472C4',  // ARGB hex
        'header_font_color'    => 'FFFFFF',  // ARGB hex
        'wrap_text'            => true,
        'freeze_header'        => true,
        'auto_filter'          => true,
        'serial_number_header' => 'S.N.',
    ],
];
```

All values can be set via environment variables — see the config file for `env()` keys.

## JSON Response Format

**`store()` response:**

```json
{
    "success": true,
    "url": "https://your-app.com/storage/exports/users_2024-01-15_143022.xlsx",
    "filename": "users_2024-01-15_143022.xlsx",
    "message": "File exported successfully."
}
```

**`queue()` response (HTTP 202):**

```json
{
    "success": true,
    "filename": "users_2024-01-15_143022.xlsx",
    "message": "Export has been queued for processing."
}
```

## Static Analysis

PHPStan is configured at **level max** with Larastan:

```bash
composer analyse
```

## Architecture

```
src/
├── Contracts/                        # Interfaces (DIP)
│   ├── Exportable.php                # User-facing export contract
│   ├── FilenameGeneratorContract.php  # Swappable filename strategy
│   └── UrlResolverContract.php        # Swappable URL resolution
├── DataTransferObjects/              # Immutable value objects
│   ├── ExportConfig.php              # All config in one typed DTO
│   └── StyleConfig.php               # Styling config DTO
├── Enums/
│   └── ExportFormat.php              # XLSX, CSV, PDF
├── Exceptions/
│   └── ExportException.php           # Descriptive error messages
├── Exports/
│   ├── Concerns/
│   │   └── HasExportStyling.php      # Shared styling (no duplication)
│   ├── CollectionExport.php          # FromCollection exports
│   └── QueryExport.php              # FromQuery + chunked exports
├── Facades/
│   └── FileExport.php
├── Providers/
│   └── ExportServiceProvider.php
├── Services/
│   ├── ExportService.php             # Main fluent builder
│   ├── FilenameGenerator.php         # SRP: filename logic
│   └── UrlResolver.php              # SRP: URL resolution
└── Traits/
    └── HasExport.php                 # Controller convenience trait
```

## License

MIT
