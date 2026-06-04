<?php

declare(strict_types=1);

namespace Anil\FileExport\Exports;

use Anil\FileExport\DataTransferObjects\StyleConfig;
use Anil\FileExport\Engine\Contracts\ExportSheet;
use Anil\FileExport\Exports\Concerns\HasSheetFormatting;
use Closure;
use Laravel\SerializableClosure\SerializableClosure;

/**
 * Exports any iterable source — a plain array, a Generator, or an Iterator.
 *
 * Generators/iterators are consumed lazily (single pass). Array sources are
 * queue-serializable; generator sources are not, so use them for sync exports.
 */
class IterableSheet implements ExportSheet
{
    use HasSheetFormatting;

    /**
     * @param  iterable<int, mixed>  $source  The rows to export.
     * @param  array<int, string>  $headers  Column header labels.
     * @param  Closure  $mappingCallback  Maps each item to an array of cell values.
     * @param  StyleConfig  $styleConfig  Styling configuration for this sheet.
     */
    public function __construct(
        private iterable $source,
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
        foreach ($this->source as $item) {
            /** @var array<int, mixed> $mapped */
            $mapped = ($this->mappingCallback)($item);

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
