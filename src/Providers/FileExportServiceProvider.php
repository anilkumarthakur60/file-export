<?php

declare(strict_types=1);

namespace Anil\FileExport\Providers;

use Anil\FileExport\Contracts\FilenameGenerator;
use Anil\FileExport\Contracts\UrlResolver;
use Anil\FileExport\DataTransferObjects\ExportConfig;
use Anil\FileExport\Services\ExportCompletionHandler;
use Anil\FileExport\Services\FileExporter;
use Anil\FileExport\Services\TemporaryUrlResolver;
use Anil\FileExport\Services\TimestampedFilenameGenerator;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the file-export package in the Laravel service container.
 *
 * Binds all contracts to their default implementations and publishes
 * the config file. Override any binding in your AppServiceProvider
 * to swap implementations.
 */
final class FileExportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/file-export.php', 'file-export');

        $this->app->singleton(ExportConfig::class, function (): ExportConfig {
            /** @var array<string, mixed> $rawConfig */
            $rawConfig = config('file-export', []);

            return ExportConfig::fromArray($rawConfig);
        });

        $this->app->bind(FilenameGenerator::class, TimestampedFilenameGenerator::class);
        $this->app->bind(UrlResolver::class, TemporaryUrlResolver::class);
        $this->app->bind(ExportCompletionHandler::class);
        $this->app->bind(FileExporter::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'file-export');

        $this->publishes([
            __DIR__.'/../../config/file-export.php' => config_path('file-export.php'),
        ], 'file-export-config');

        $this->publishes([
            __DIR__.'/../../resources/views' => base_path('resources/views/vendor/file-export'),
        ], 'file-export-views');
    }
}
