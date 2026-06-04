<?php

declare(strict_types=1);

namespace Anil\FileExport\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired before an import begins reading.
 */
final class ReadingImport
{
    use Dispatchable;

    public function __construct(
        public readonly object $importable,
    ) {}
}
