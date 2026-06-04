<?php

declare(strict_types=1);

namespace Anil\FileExport\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after an import has finished reading.
 */
final class ImportRead
{
    use Dispatchable;

    public function __construct(
        public readonly object $importable,
    ) {}
}
