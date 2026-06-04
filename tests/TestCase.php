<?php

declare(strict_types=1);

namespace Anil\FileExport\Tests;

use Anil\FileExport\Contracts\FilenameGenerator;
use Anil\FileExport\Enums\ExportFormat;
use Anil\FileExport\Providers\FileExportServiceProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\ExcelServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function ($table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Deterministic filenames so assertions can target an exact path.
        $this->app->bind(FilenameGenerator::class, fn () => new class implements FilenameGenerator
        {
            public function generate(?string $customName, ExportFormat $format): string
            {
                return ($customName ?? 'export').'.'.$format->extension();
            }
        });
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ExcelServiceProvider::class,
            FileExportServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        /** @var Application $app */
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('filesystems.disks.local', [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
        ]);

        $app['config']->set('file-export.disk', 'local');
    }
}
