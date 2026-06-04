<?php

declare(strict_types=1);

namespace Anil\FileExport\Import\Contracts;

/**
 * Validates each row against Laravel validation rules before it is consumed.
 */
interface ValidatesRows
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array;
}
