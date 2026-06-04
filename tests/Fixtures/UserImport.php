<?php

declare(strict_types=1);

namespace Anil\FileExport\Tests\Fixtures;

use Anil\FileExport\Import\Contracts\MapsToModel;
use Anil\FileExport\Import\Contracts\UsesHeadingRow;
use Illuminate\Database\Eloquent\Model;

/**
 * Test importable: maps heading-keyed rows to User models.
 */
class UserImport implements MapsToModel, UsesHeadingRow
{
    public function headingRow(): int
    {
        return 1;
    }

    /**
     * @param  array<int|string, mixed>  $row
     */
    public function model(array $row): Model|array|null
    {
        return new User([
            'name' => $row['name'] ?? null,
            'email' => $row['email'] ?? null,
        ]);
    }
}
