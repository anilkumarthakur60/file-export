<?php

declare(strict_types=1);

use Anil\FileExport\Services\FileExporter;
use Anil\FileExport\Tests\Fixtures\User;
use Anil\FileExport\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Bind the Testbench-based TestCase (Maatwebsite + package providers, in-memory
| sqlite, local disk) to every test in the Feature suite.
|
*/

uses(TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
|
| Shared across the Feature suite. Defined here once so individual test files
| never redeclare global helpers (which would collide when Pest loads them all).
|
*/

function fileExporter(): FileExporter
{
    return app(FileExporter::class);
}

function seedUsers(int $count): void
{
    for ($i = 1; $i <= $count; $i++) {
        User::query()->create([
            'name' => "User {$i}",
            'email' => "user{$i}@example.com",
        ]);
    }
}
