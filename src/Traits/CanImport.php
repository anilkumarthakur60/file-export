<?php

declare(strict_types=1);

namespace Anil\FileExport\Traits;

use Anil\FileExport\Services\FileImporter;
use Illuminate\Support\Collection;

/**
 * Convenience trait for controllers that need simple import functionality.
 *
 * For advanced configuration, inject FileImporter directly via constructor.
 *
 * @phpstan-require-extends \Illuminate\Routing\Controller
 */
trait CanImport
{
    /**
     * Read a file into a nested array of rows.
     *
     * @return array<int, array<int, array<int|string, mixed>>>
     */
    protected function importArray(string $file, ?string $disk = null): array
    {
        return $this->fileImporter()->from($file, $disk)->toArray();
    }

    /**
     * Read a file into a Collection of row Collections.
     *
     * @return Collection<int, Collection<int, array<int|string, mixed>>>
     */
    protected function importCollection(string $file, ?string $disk = null): Collection
    {
        return $this->fileImporter()->from($file, $disk)->toCollection();
    }

    /**
     * Drive an importable over a file.
     */
    protected function importUsing(object $importable, string $file, ?string $disk = null): void
    {
        $this->fileImporter()->from($file, $disk)->import($importable);
    }

    /**
     * Get a fresh FileImporter instance for advanced configuration.
     */
    protected function fileImporter(): FileImporter
    {
        /** @var FileImporter */
        return app(FileImporter::class);
    }
}
