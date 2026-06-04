<?php

declare(strict_types=1);

namespace Anil\FileExport\Jobs;

use Anil\FileExport\Events\ImportFailed;
use Anil\FileExport\Services\FileImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Runs a full import on the queue (non-chunked).
 */
final class ImportFile implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly object $importable,
        public readonly string $file,
        public readonly ?string $disk = null,
    ) {}

    public function handle(FileImporter $importer): void
    {
        $importer->from($this->file, $this->disk)->import($this->importable);
    }

    public function failed(Throwable $exception): void
    {
        event(new ImportFailed($this->importable, $exception));
    }
}
