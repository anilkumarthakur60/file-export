<?php

declare(strict_types=1);

namespace Anil\FileExport\Exports;

use Anil\FileExport\DataTransferObjects\StyleConfig;
use Anil\FileExport\Engine\Contracts\ExportSheet;
use Anil\FileExport\Engine\Contracts\RendersHtml;
use Anil\FileExport\Exports\Concerns\HasSheetFormatting;
use Closure;
use Illuminate\Contracts\View\View;
use Laravel\SerializableClosure\SerializableClosure;

/**
 * Exports a rendered Blade view as a sheet.
 *
 * The view is rendered to HTML and loaded into the worksheet, so the view's
 * own table markup defines the layout. Best for small-to-medium documents
 * (the HTML is built in memory).
 */
class ViewSheet implements ExportSheet, RendersHtml
{
    /**
     * @param  View|(Closure(): View)  $view  A view instance or a factory returning one.
     */
    public function __construct(
        private View|Closure $view,
        StyleConfig $styleConfig = new StyleConfig,
    ) {
        $this->initializeFormatting($styleConfig);
    }

    use HasSheetFormatting;

    public function autoSize(): bool
    {
        return false;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [];
    }

    /**
     * @return iterable<int, array<int, mixed>>
     */
    public function rows(): iterable
    {
        return [];
    }

    public function html(): string
    {
        $view = $this->view instanceof Closure ? ($this->view)() : $this->view;

        return $view->render();
    }

    /**
     * @return array<mixed>
     */
    public function __serialize(): array
    {
        $data = get_object_vars($this);

        if ($this->view instanceof Closure) {
            $data['view'] = new SerializableClosure($this->view);
        }

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
