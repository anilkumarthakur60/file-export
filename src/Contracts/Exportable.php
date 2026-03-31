<?php

declare(strict_types=1);

namespace Anil\FileExport\Contracts;

interface Exportable
{
    /**
     * @return array<int, string>
     */
    public function headers(): array;

    /**
     * @return array<int, mixed>
     */
    public function map(mixed $row): array;
}
