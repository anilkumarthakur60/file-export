<?php

declare(strict_types=1);

namespace Anil\FileExport\Traits;

use Anil\FileExport\Contracts\Exportable;
use Anil\FileExport\DataTransferObjects\ExportResult;
use Anil\FileExport\Services\FileExporter;
use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

/**
 * Convenience trait for controllers that need simple export functionality.
 *
 * For advanced configuration, inject FileExporter directly via constructor.
 *
 * @phpstan-require-extends \Illuminate\Routing\Controller
 */
trait CanExport
{
    /**
     * Export data from a query builder to disk and return the result.
     *
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed>  $builder
     * @param  array<int, string>  $headers
     */
    protected function export(EloquentBuilder|Relation $builder, array $headers, Closure $mapping): ExportResult
    {
        return $this->fileExporter()
            ->fromQuery($builder)
            ->withHeaders($headers)
            ->withMapping($mapping)
            ->store();
    }

    /**
     * Export data from a collection to disk and return the result.
     *
     * @param  Collection<int, mixed>  $collection
     * @param  array<int, string>  $headers
     */
    protected function exportCollection(Collection $collection, array $headers, Closure $mapping): ExportResult
    {
        return $this->fileExporter()
            ->fromCollection($collection)
            ->withHeaders($headers)
            ->withMapping($mapping)
            ->store();
    }

    /**
     * Export data using a reusable Exportable class.
     *
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed>|Collection<int, mixed>  $source
     */
    protected function exportUsing(Exportable $exportable, EloquentBuilder|Relation|Collection $source): ExportResult
    {
        return $this->fileExporter()
            ->fromExportable($exportable, $source)
            ->store();
    }

    /**
     * Get a fresh FileExporter instance for advanced configuration.
     */
    protected function fileExporter(): FileExporter
    {
        /** @var FileExporter */
        return app(FileExporter::class);
    }
}
