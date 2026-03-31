<?php

declare(strict_types=1);

namespace Anil\FileExport\Facades;

use Anil\FileExport\Services\FileExporter;
use Illuminate\Support\Facades\Facade;

/**
 * Static facade for the FileExporter service.
 *
 * Provides convenient static access to the fluent export API.
 * For full testability, prefer injecting FileExporter directly.
 *
 * @mixin FileExporter
 */
final class FileExport extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return FileExporter::class;
    }
}
