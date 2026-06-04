<?php

declare(strict_types=1);

namespace Anil\FileExport\Exports;

use Anil\FileExport\DataTransferObjects\StyleConfig;
use Anil\FileExport\Engine\Contracts\ExportSheet;
use Anil\FileExport\Exports\Concerns\HasSheetFormatting;
use Closure;
use Illuminate\Support\Collection;
use Laravel\SerializableClosure\SerializableClosure;

/**
 * Exports data from an in-memory Collection.
 *
 * Suitable for small-to-medium datasets already loaded in memory. For large
 * datasets, use QueryExport (streamed from the database) instead.
 */
class CollectionExport implements ExportSheet
{
    use HasSheetFormatting;

    /**
     * @param  Collection<int, mixed>  $data  The data to export.
     * @param  array<int, string>  $headers  Column header labels.
     * @param  Closure  $mappingCallback  Maps each row to an array of cell values.
     * @param  StyleConfig  $styleConfig  Styling configuration for this sheet.
     */
    public function __construct(
        private readonly Collection $data,
        private readonly array $headers,
        private Closure $mappingCallback,
        StyleConfig $styleConfig = new StyleConfig,
    ) {
        $this->initializeFormatting($styleConfig);
    }

    public function autoSize(): bool
    {
        return true;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return $this->prependSerialNumberHeader($this->headers);
    }

    /**
     * @return iterable<int, array<int, mixed>>
     */
    public function rows(): iterable
    {
        foreach ($this->data as $row) {
            /** @var array<int, mixed> $mapped */
            $mapped = ($this->mappingCallback)($row);

            yield $this->prependSerialNumber($mapped);
        }
    }

    /**
     * @return array<mixed>
     */
    public function __serialize(): array
    {
        $data = get_object_vars($this);

        $data['mappingCallback'] = new SerializableClosure($this->mappingCallback);

        if ($this->styleCallback instanceof Closure) {
            $data['styleCallback'] = new SerializableClosure($this->styleCallback);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function __unserialize(array $data): void
    {
        foreach ($data as $key => $value) {
            if ($value instanceof SerializableClosure) {
                $value = $value->getClosure();
            }

            $this->{$key} = $value;
        }
    }
}
