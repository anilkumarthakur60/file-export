<?php

declare(strict_types=1);

namespace Anil\FileExport\Services;

use Anil\FileExport\DataTransferObjects\ExportConfig;
use Anil\FileExport\Exceptions\FileImportException;
use Anil\FileExport\Import\Contracts\ReadsChunked;
use Anil\FileExport\Import\LocalFile;
use Anil\FileExport\Import\SpreadsheetReader;
use Anil\FileExport\Jobs\ImportFile;
use Anil\FileExport\Jobs\ReadImportChunk;
use Closure;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;

/**
 * Fluent entry point for reading spreadsheet files.
 *
 * Usage:
 *   $importer->from('users.xlsx')->toArray();
 *   $importer->from('users.xlsx', 's3')->import(new UserImport);
 */
final class FileImporter
{
    use Conditionable;
    use Macroable;

    private ?string $file = null;

    private ?string $disk = null;

    public function __construct(
        private readonly SpreadsheetReader $reader,
        private readonly FilesystemManager $filesystem,
        private readonly ExportConfig $config,
    ) {}

    /**
     * Set the source file. Pass an absolute local path (no disk), or a path on
     * the given/default filesystem disk.
     */
    public function from(string $file, ?string $disk = null): self
    {
        $this->file = $file;
        $this->disk = $disk;

        return $this;
    }

    /**
     * Read every sheet into a nested array of rows.
     *
     * @return array<int, array<int, array<int|string, mixed>>>
     */
    public function toArray(): array
    {
        return $this->withLocalFile(fn (string $path): array => $this->reader->toArray($path));
    }

    /**
     * Read every sheet into a Collection of row Collections.
     *
     * @return Collection<int, Collection<int, array<int|string, mixed>>>
     */
    public function toCollection(): Collection
    {
        return $this->withLocalFile(fn (string $path): Collection => $this->reader->toCollection($path));
    }

    /**
     * Drive an importable (ReceivesArray/ReceivesCollection/HandlesEachRow/MapsToModel).
     */
    public function import(object $importable): void
    {
        $this->withLocalFile(function (string $path) use ($importable): null {
            $this->reader->import($importable, $path);

            return null;
        });
    }

    /**
     * Queue the import. Importables implementing ReadsChunked are split into
     * one queued job per row window; others run as a single queued job.
     *
     * @throws FileImportException
     */
    public function queue(object $importable): void
    {
        $file = $this->file ?? throw FileImportException::missingFile();

        if (! $importable instanceof ReadsChunked) {
            ImportFile::dispatch($importable, $file, $this->disk);

            return;
        }

        [$path, $isTemporary] = $this->resolveLocalFile();
        try {
            $total = $this->reader->rowCount($path);
        } finally {
            if ($isTemporary) {
                @unlink($path);
            }
        }

        $start = $this->reader->dataStartRow($importable);
        $size = max(1, $importable->chunkSize());

        for ($from = $start; $from <= $total; $from += $size) {
            ReadImportChunk::dispatch($importable, $file, $this->disk, $from, min($from + $size - 1, $total));
        }
    }

    /**
     * Resolve the source to a local path, run the callback, and clean up any temp copy.
     *
     * @template T
     *
     * @param  Closure(string): T  $callback
     * @return T
     *
     * @throws FileImportException
     */
    private function withLocalFile(Closure $callback): mixed
    {
        [$path, $isTemporary] = $this->resolveLocalFile();

        try {
            return $callback($path);
        } finally {
            if ($isTemporary) {
                @unlink($path);
            }
        }
    }

    /**
     * @return array{0: string, 1: bool}  [localPath, isTemporary]
     *
     * @throws FileImportException
     */
    private function resolveLocalFile(): array
    {
        $file = $this->file ?? throw FileImportException::missingFile();

        return LocalFile::resolve($this->filesystem, $file, $this->disk, $this->config->disk);
    }
}
