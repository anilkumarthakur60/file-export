<?php

declare(strict_types=1);

namespace Anil\FileExport\Exports;

use Maatwebsite\Excel\Concerns\ShouldAutoSize;

/**
 * Exports data from an Eloquent query builder with automatic chunking and
 * auto-sized columns.
 *
 * Suitable for small-to-large datasets where the row count fits comfortably
 * within a single sheet. For exports large enough to be auto-split (or where
 * per-cell column measuring would be prohibitively slow), LargeQueryExport is
 * used instead — it is identical but omits ShouldAutoSize.
 */
class QueryExport extends BaseQueryExport implements ShouldAutoSize {}
