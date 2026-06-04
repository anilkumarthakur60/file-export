<?php

declare(strict_types=1);

namespace Anil\FileExport\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Throwable;

/**
 * Fired when a queued import job fails.
 */
final class ImportFailed
{
    use Dispatchable;

    public function __construct(
        public readonly object $importable,
        public readonly Throwable $exception,
    ) {}
}
