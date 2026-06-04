<?php

declare(strict_types=1);

namespace Anil\FileExport\Import\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Maps each read row to an Eloquent model (or models) to be persisted.
 */
interface MapsToModel
{
    /**
     * @param  array<int|string, mixed>  $row
     * @return Model|array<int, Model>|null
     */
    public function model(array $row): Model|array|null;
}
