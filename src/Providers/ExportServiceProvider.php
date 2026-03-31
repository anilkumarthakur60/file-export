<?php

declare(strict_types=1);

namespace Anil\FileExport\Providers;

use Anil\FileExport\Contracts\FilenameGeneratorContract;
use Anil\FileExport\Contracts\UrlResolverContract;
use Anil\FileExport\DataTransferObjects\ExportConfig;
use Anil\FileExport\Services\ExportService;
use Anil\FileExport\Services\FilenameGenerator;
use Anil\FileExport\Services\UrlResolver;
use Illuminate\Support\ServiceProvider;

final class ExportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/file-export.php', 'file-export');

        $this->app->singleton(ExportConfig::class, function (): ExportConfig {
            /** @var array<string, mixed> $config */
            $config = config('file-export', []);

            return ExportConfig::fromArray($config);
        });

        $this->app->bind(FilenameGeneratorContract::class, FilenameGenerator::class);
        $this->app->bind(UrlResolverContract::class, UrlResolver::class);

        $this->app->bind(ExportService::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/file-export.php' => config_path('file-export.php'),
        ], 'file-export-config');
    }
}
