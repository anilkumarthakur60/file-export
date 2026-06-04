<?php

declare(strict_types=1);

namespace Anil\FileExport\Import;

/**
 * A single read row, with optional heading-row keys.
 *
 * When headings are present, toArray() returns an associative array keyed by
 * the (formatted) heading labels; otherwise it returns the raw cell values.
 */
final class ImportRow
{
    /**
     * @param  array<int, mixed>  $values  Raw cell values, left-to-right.
     * @param  array<int, string>  $headings  Formatted heading labels (empty for headingless reads).
     * @param  int  $index  The 1-based worksheet row number.
     */
    public function __construct(
        private readonly array $values,
        private readonly array $headings,
        private readonly int $index,
    ) {}

    /**
     * The row as an array — associative when headings are present.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(): array
    {
        if ($this->headings === []) {
            return $this->values;
        }

        $result = [];
        foreach ($this->headings as $i => $heading) {
            $result[$heading] = $this->values[$i] ?? null;
        }

        return $result;
    }

    /**
     * The 1-based worksheet row number.
     */
    public function index(): int
    {
        return $this->index;
    }

    /**
     * Whether every cell in the row is null or empty string.
     */
    public function isEmpty(): bool
    {
        foreach ($this->values as $value) {
            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }
}
