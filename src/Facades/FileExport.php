<?php

declare(strict_types=1);

namespace Anil\FileExport\Facades;

use Anil\FileExport\Services\ExportService;
use Illuminate\Support\Facades\Facade;

/**
 * @mixin ExportService
 */
final class FileExport extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ExportService::class;
    }
}
