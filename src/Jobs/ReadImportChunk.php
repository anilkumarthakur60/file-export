<?php

declare(strict_types=1);

namespace Anil\FileExport\Jobs;

use Anil\FileExport\DataTransferObjects\ExportConfig;
use Anil\FileExport\Events\ImportFailed;
use Anil\FileExport\Import\LocalFile;
use Anil\FileExport\Import\SpreadsheetReader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Reads and imports a single row window of a file — one queued job per chunk.
 */
final class ReadImportChunk implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly object $importable,
        public readonly string $file,
        public readonly ?string $disk,
        public readonly int $startRow,
        public readonly int $endRow,
    ) {}

    public function handle(SpreadsheetReader $reader, FilesystemManager $filesystem, ExportConfig $config): void
    {
        [$path, $isTemporary] = LocalFile::resolve($filesystem, $this->file, $this->disk, $config->disk);

        try {
            $reader->importChunk($this->importable, $path, $this->startRow, $this->endRow);
        } finally {
            if ($isTemporary) {
                @unlink($path);
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        event(new ImportFailed($this->importable, $exception));
    }
}
