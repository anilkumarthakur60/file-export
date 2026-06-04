<?php

declare(strict_types=1);

namespace Anil\FileExport\Services;

use Anil\FileExport\DataTransferObjects\ExportConfig;
use Anil\FileExport\Exceptions\FileImportException;
use Anil\FileExport\Import\SpreadsheetReader;
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

        // Absolute/local path used directly when no disk is specified.
        if ($this->disk === null && is_file($file)) {
            return [$file, false];
        }

        $storage = $this->filesystem->disk($this->disk ?? $this->config->disk);

        if (! $storage->exists($file)) {
            throw FileImportException::unreadableFile($file);
        }

        // Use the disk's real path when it is local.
        try {
            $local = $storage->path($file);
            if (is_file($local)) {
                return [$local, false];
            }
        } catch (\Throwable) {
            // Disk has no local path (e.g. S3) — fall through to a temp copy.
        }

        $extension = pathinfo($file, PATHINFO_EXTENSION) ?: 'tmp';
        $temporary = sys_get_temp_dir().'/'.uniqid('fimp_', true).'.'.$extension;

        $stream = $storage->readStream($file);
        if ($stream === null) {
            throw FileImportException::unreadableFile($file);
        }

        file_put_contents($temporary, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        return [$temporary, true];
    }
}
