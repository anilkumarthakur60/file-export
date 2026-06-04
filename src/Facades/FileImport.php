<?php

declare(strict_types=1);

namespace Anil\FileExport\Facades;

use Anil\FileExport\Services\FileImporter;
use Illuminate\Support\Facades\Facade;

/**
 * Static facade for the FileImporter service.
 *
 * @mixin FileImporter
 */
final class FileImport extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return FileImporter::class;
    }
}
