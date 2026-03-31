# Laravel File Export

A powerful, fluent file export package for Laravel built on top of [Maatwebsite/Excel](https://laravel-excel.com/). SOLID architecture, PHPStan level max, multi-sheet support — works for any project size.

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

## Quick Start (Beginner)

### Controller Trait — One-liner Export

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
        )->toResponse();
    }
}
```

### Facade — Fluent API

```php
use Anil\FileExport\Facades\FileExport;

return FileExport::fromQuery(User::query())
    ->withHeaders(['Name', 'Email'])
    ->withMapping(fn (User $user) => [$user->name, $user->email])
    ->store()
    ->toResponse();
```

---

## Intermediate Usage

### Export Formats

```php
use Anil\FileExport\Enums\ExportFormat;

->format(ExportFormat::XLSX)   // Excel (default)
->format(ExportFormat::CSV)    // CSV
->format(ExportFormat::TSV)    // Tab-separated
->format(ExportFormat::ODS)    // OpenDocument
->format(ExportFormat::HTML)   // HTML table
->format(ExportFormat::PDF)    // PDF (requires mpdf/mpdf)
```

### Output Modes

```php
// Store to disk → ExportResult (with URL, path, filename)
$result = FileExport::fromQuery(User::query())
    ->withHeaders(['Name', 'Email'])
    ->withMapping(fn ($u) => [$u->name, $u->email])
    ->store();

$result->url;       // Download URL
$result->filename;  // Generated filename
$result->path;      // Storage path
$result->toResponse();  // JsonResponse
$result->toArray();     // Raw array

// Direct browser download
return FileExport::fromQuery(User::query())
    ->withHeaders(['Name', 'Email'])
    ->withMapping(fn ($u) => [$u->name, $u->email])
    ->download();

// Queue for background processing
$result = FileExport::fromQuery(User::query())
    ->withHeaders(['Name', 'Email'])
    ->withMapping(fn ($u) => [$u->name, $u->email])
    ->queue();

// Raw binary content (for API responses, email attachments, etc.)
$binary = FileExport::fromQuery(User::query())
    ->withHeaders(['Name', 'Email'])
    ->withMapping(fn ($u) => [$u->name, $u->email])
    ->raw();
```

### From a Collection

```php
$result = FileExport::fromCollection(collect($data))
    ->withHeaders(['Key', 'Value'])
    ->withMapping(fn (array $item) => [$item['key'], $item['value']])
    ->store();
```

### Serial Numbers, Limits, Custom Filename

```php
$result = FileExport::fromQuery(Order::where('status', 'completed'))
    ->withHeaders(['Order #', 'Customer', 'Total'])
    ->withMapping(fn (Order $o) => [$o->number, $o->customer->name, $o->total])
    ->withSerialNumber()
    ->withLimit(5000)
    ->filename('completed-orders')
    ->disk('s3')
    ->store();
```

### Column Formatting & Widths

```php
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

$result = FileExport::fromQuery(Invoice::query())
    ->withHeaders(['Date', 'Amount', 'Notes'])
    ->withMapping(fn (Invoice $i) => [$i->date, $i->amount, $i->notes])
    ->withColumnFormats([
        'A' => NumberFormat::FORMAT_DATE_DDMMYYYY,
        'B' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
    ])
    ->withColumnWidths([
        'A' => 15,
        'B' => 20,
        'C' => 50,
    ])
    ->store();
```

---

## Advanced Usage

### Multi-Sheet Exports

```php
use Anil\FileExport\Builders\SheetBuilder;

$result = FileExport::addSheet('Active Users', fn (SheetBuilder $sheet) => $sheet
        ->fromQuery(User::where('active', true))
        ->withHeaders(['Name', 'Email'])
        ->withMapping(fn (User $u) => [$u->name, $u->email])
    )
    ->addSheet('Inactive Users', fn (SheetBuilder $sheet) => $sheet
        ->fromQuery(User::where('active', false))
        ->withHeaders(['Name', 'Email', 'Deactivated At'])
        ->withMapping(fn (User $u) => [$u->name, $u->email, $u->deactivated_at])
    )
    ->withDocumentProperties([
        'title' => 'User Report',
        'creator' => 'System',
        'description' => 'Monthly user export',
    ])
    ->filename('user-report')
    ->store();
```

Each sheet gets its own headers, mapping, styling, serial numbers, and data source.

### Exportable Contract — Reusable Export Classes

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
$result = FileExport::fromExportable(new UserExport, User::with('role')->query())->store();
```

### Custom Styling per Export

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

$result = FileExport::fromQuery(User::query())
    ->withHeaders(['Name', 'Email'])
    ->withMapping(fn (User $u) => [$u->name, $u->email])
    ->withStyleConfig($style)
    ->store();
```

### Document Properties

```php
$result = FileExport::fromQuery(User::query())
    ->withHeaders(['Name', 'Email'])
    ->withMapping(fn ($u) => [$u->name, $u->email])
    ->withDocumentProperties([
        'title' => 'User List',
        'creator' => 'Admin System',
        'company' => 'Acme Corp',
        'description' => 'Exported user data',
    ])
    ->store();
```

### Dependency Injection (Testable)

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
            ->store()
            ->toResponse();
    }
}
```

### Swapping Implementations (OCP / DIP)

Override filename generation or URL resolution in your `AppServiceProvider`:

```php
use Anil\FileExport\Contracts\FilenameGeneratorContract;
use Anil\FileExport\Contracts\UrlResolverContract;

$this->app->bind(FilenameGeneratorContract::class, MyCustomFilenameGenerator::class);
$this->app->bind(UrlResolverContract::class, CdnUrlResolver::class);
```

---

## ExportResult DTO

`store()` and `queue()` return an `ExportResult` value object:

```php
$result = FileExport::fromQuery(User::query())
    ->withHeaders(['Name'])
    ->withMapping(fn ($u) => [$u->name])
    ->store();

$result->filename;  // "users_2024-01-15_143022.xlsx"
$result->url;       // "https://your-app.com/storage/exports/..."
$result->path;      // "exports/users_2024-01-15_143022.xlsx"
$result->disk;      // "public"

// Convert to JSON response
return $result->toResponse();

// Or get raw array
$data = $result->toArray();
```

---

## Configuration

```php
// config/file-export.php
return [
    'disk'             => 'public',
    'path'             => 'exports',
    'expire_days'      => 7,
    'default_format'   => 'xlsx',       // xlsx, csv, tsv, ods, html, pdf
    'max_rows'         => 1000000,
    'chunk_size'       => 1000,
    'timestamp_format' => 'Y-m-d_His',

    'styling' => [
        'header_bold'          => true,
        'header_background'    => '4472C4',
        'header_font_color'    => 'FFFFFF',
        'wrap_text'            => true,
        'freeze_header'        => true,
        'auto_filter'          => true,
        'serial_number_header' => 'S.N.',
    ],
];
```

All values support `env()` overrides — see the config file for keys.

## Static Analysis

```bash
composer analyse
```

PHPStan configured at **level max** with Larastan — 0 errors.

---

## Architecture

```
src/
├── Builders/
│   └── SheetBuilder.php              # Sheet-level configuration builder
├── Contracts/
│   ├── Exportable.php                # User export contract
│   ├── FilenameGeneratorContract.php  # Swappable filename strategy
│   └── UrlResolverContract.php        # Swappable URL resolution
├── DataTransferObjects/
│   ├── ExportConfig.php              # Typed config DTO
│   ├── ExportResult.php              # Store/queue result DTO
│   └── StyleConfig.php              # Styling config DTO
├── Enums/
│   └── ExportFormat.php              # XLSX, CSV, TSV, ODS, HTML, PDF
├── Exceptions/
│   └── ExportException.php           # Descriptive named constructors
├── Exports/
│   ├── Concerns/
│   │   └── HasExportStyling.php      # Shared styling (zero duplication)
│   ├── CollectionExport.php          # FromCollection exports
│   ├── MultiSheetExport.php          # Multi-sheet wrapper
│   └── QueryExport.php              # FromQuery + chunked exports
├── Facades/
│   └── FileExport.php
├── Providers/
│   └── ExportServiceProvider.php
├── Services/
│   ├── ExportService.php             # Main fluent API
│   ├── FilenameGenerator.php         # SRP: filename logic
│   └── UrlResolver.php              # SRP: URL resolution
└── Traits/
    └── HasExport.php                 # Controller convenience trait
```

## License

MIT
