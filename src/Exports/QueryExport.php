<?php

declare(strict_types=1);

namespace Anil\FileExport\Exports;

/**
 * Exports an Eloquent query with auto-sized columns.
 *
 * Suitable for small-to-large datasets that fit within a single sheet. For
 * exports large enough to be auto-split (or where per-cell column measuring
 * would be prohibitively slow), LargeQueryExport is used — it is identical but
 * does not auto-size.
 */
class QueryExport extends BaseQueryExport
{
    public function autoSize(): bool
    {
        return true;
    }
}
