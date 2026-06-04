<?php

declare(strict_types=1);

namespace Anil\FileExport\Import;

use Illuminate\Support\Str;

/**
 * Formats heading-row cell values into array keys.
 *
 * Default behaviour slugs each heading with underscores ("First Name" →
 * "first_name"), matching the conventional spreadsheet-import style.
 */
final class HeadingFormatter
{
    /**
     * @param  array<int, mixed>  $headings
     * @return array<int, string>
     */
    public function format(array $headings): array
    {
        return array_map(
            static fn ($heading): string => Str::slug(is_scalar($heading) ? (string) $heading : '', '_'),
            $headings,
        );
    }
}
