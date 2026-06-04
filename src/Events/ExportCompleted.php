<?php

declare(strict_types=1);

namespace Anil\FileExport\Events;

use Anil\FileExport\DataTransferObjects\ExportResult;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when an export has finished writing to disk.
 *
 * Fires on both the synchronous store() path and after a queued export's
 * write jobs complete. Listen for it to react to finished exports without
 * coupling to the export call site:
 *
 *   Event::listen(ExportCompleted::class, function (ExportCompleted $e) {
 *       Log::info('Export ready: '.$e->result->url);
 *   });
 */
final class ExportCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly ExportResult $result,
    ) {}
}
