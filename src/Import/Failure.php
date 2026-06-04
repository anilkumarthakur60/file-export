<?php

declare(strict_types=1);

namespace Anil\FileExport\Import;

/**
 * A single row validation failure.
 */
final class Failure
{
    /**
     * @param  int  $row  1-based worksheet row number.
     * @param  array<int, string>  $errors  Validation error messages.
     * @param  array<int|string, mixed>  $values  The row values that failed.
     */
    public function __construct(
        public readonly int $row,
        public readonly array $errors,
        public readonly array $values,
    ) {}
}
