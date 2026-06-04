<?php

declare(strict_types=1);

namespace Anil\FileExport\Engine\Contracts;

/**
 * A sheet whose content is produced as an HTML string (e.g. a rendered Blade
 * view) and loaded into the worksheet, rather than written row-by-row.
 */
interface RendersHtml
{
    /**
     * The HTML to load into the worksheet.
     */
    public function html(): string;
}
