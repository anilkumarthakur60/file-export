<?php

namespace Anil\FileExport\Providers;

use Illuminate\Support\ServiceProvider;

class ExportServiceProvider extends ServiceProvider
{
    public function register(): void
    {

        $this->mergeConfigFrom(__DIR__.'/../../config/fileExport.php', 'fileExport');
        
    }

    public function boot(): void
    {

        $this->publishes([
            __DIR__.'/../../config/fileExport.php' => config_path('fileExport.php'),
        ], 'config');
    }
}
