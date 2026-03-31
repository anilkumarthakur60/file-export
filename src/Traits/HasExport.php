<?php

declare(strict_types=1);

namespace Anil\FileExport\Traits;

use Anil\FileExport\Contracts\Exportable;
use Anil\FileExport\Services\ExportService;
use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

/**
 * @phpstan-require-extends \Illuminate\Routing\Controller
 */
trait HasExport
{
    /**
     * Quick export from a query builder with headers and mapping.
     *
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed>  $builder
     * @param  array<int, string>  $headers
     */
    protected function export(EloquentBuilder|Relation $builder, array $headers, Closure $mapping): JsonResponse
    {
        return $this->exporter()
            ->fromQuery($builder)
            ->withHeaders($headers)
            ->withMapping($mapping)
            ->store();
    }

    /**
     * Quick export from a collection with headers and mapping.
     *
     * @param  Collection<int, mixed>  $collection
     * @param  array<int, string>  $headers
     */
    protected function exportCollection(Collection $collection, array $headers, Closure $mapping): JsonResponse
    {
        return $this->exporter()
            ->fromCollection($collection)
            ->withHeaders($headers)
            ->withMapping($mapping)
            ->store();
    }

    /**
     * Export using an Exportable class.
     *
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed>|Collection<int, mixed>  $source
     */
    protected function exportUsing(Exportable $exportable, EloquentBuilder|Relation|Collection $source): JsonResponse
    {
        return $this->exporter()
            ->fromExportable($exportable, $source)
            ->store();
    }

    protected function exporter(): ExportService
    {
        /** @var ExportService */
        return app(ExportService::class);
    }
}
