# Changelog

All notable changes to `anil/file-export` are documented here. This project
adheres to [Semantic Versioning](https://semver.org).

## 2.0.0

A major release focused on **scale** (small projects → 10M+ row databases),
**delivery** (email, queued completion hooks), and **developer experience**.
v2 does **not** maintain backward compatibility with v1.

### Added

- **Email delivery** — `->notifyMail($recipients)` sends the export, attaching the
  file when it's at/below `mail.attach_threshold` bytes or emailing a (signed)
  download link otherwise. Ships a responsive, dark-mode-aware, publishable
  Blade template (`file-export-views` tag).
- **Completion hooks** (sync `store()` *and* queued):
  - `ExportCompleted` event (always fired; `->withCompletionEvent(false)` to disable),
  - `->notify($notifiable, $notification)` — Laravel Notifications,
  - `->onCompletion(Closure)` — callback receiving the `ExportResult`,
  - auto-email via `->notifyMail()`.
- **Auto-split** — single-sheet xlsx/ods query exports exceeding the per-sheet
  row limit are transparently split across multiple sheets, with continuous
  serial numbering. `->autoSplit(bool, ?rowsPerSheet)` + `auto_split` config.
- **Queued query exports** — pass a factory closure (`fn () => User::query()`) so
  the query is rebuilt inside the job; mapping callbacks are serialized
  automatically. `->onConnection()` / `->onQueue()` + `queue` config.
- **CSV/TSV settings** — `->withCsvSettings(new CsvSettings(...))` (delimiter,
  enclosure, line ending, UTF-8 BOM, …) + `csv` config section.
- **`max_rows` enforcement** — now actually guards spreadsheet exports
  (previously parsed but unused); set to `null` for unlimited CSV dumps.
- **Conditionable & Macroable** — `->when()` / `->unless()` and
  `FileExporter::macro()` for fluent, extensible pipelines.
- **Richer `ExportResult`** — now also exposes `format` and `mimeType`.
- **Test suite** — comprehensive [Pest](https://pestphp.com/) suite (runs in
  parallel), in addition to PHPStan level max.

### Changed / Breaking

- `FileExporter` now depends on `ExportCompletionHandler` (constructor arg) and
  receives the Excel service via the `Maatwebsite\Excel\Exporter` **interface**
  instead of the concrete `Excel` class.
- `ExportResult::toArray()` now includes `format` and `mime_type` keys.
- `->queue()` on a query export now **requires a factory closure** and throws a
  `FileExportException` if given a live Eloquent builder (which can't be
  serialized onto a queue).
- Large xlsx/ods query exports now **auto-split** (or throw when over `max_rows`
  with splitting disabled) instead of silently truncating at the row limit.
- New config sections (`queue`, `auto_split`, `mail`, `csv`) — re-publish the
  config with `php artisan vendor:publish --tag=file-export-config`.

### Requirements

- PHP 8.2+, Laravel 11/12/13, Maatwebsite/Excel 3.1+.
- `mpdf/mpdf` is required only for PDF export.

## 1.0.0

- Initial release: fluent fromQuery/fromCollection/fromExportable exports to
  xlsx/csv/tsv/ods/html/pdf, store/download/queue/raw output modes, styling,
  serial numbers, column formats/widths, multi-sheet workbooks.
