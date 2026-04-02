<?php

declare(strict_types=1);

namespace Anil\FileExport\Contracts;

/**
 * Implement this interface to create a reusable, self-contained export definition.
 *
 * Usage:
 *   class UserExport implements Exportable {
 *       public function headers(): array { return ['Name', 'Email']; }
 *       public function map(mixed $row): array { return [$row->name, $row->email]; }
 *   }
 *
 *   FileExport::fromExportable(new UserExport, User::query())->store();
 */
interface Exportable
{
    /**
     * Return the column headers for the exported file.
     *
     * @return array<int, string>
     */
    public function headers(): array;

    /**
     * Map a single data row to an array of cell values.
     *
     * Each value in the returned array corresponds to a column header
     * at the same index.
     *
     * @return array<int, mixed>
     */
    public function map(mixed $row): array;
}
