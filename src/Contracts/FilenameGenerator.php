<?php

declare(strict_types=1);

namespace Anil\FileExport\Contracts;

use Anil\FileExport\Enums\ExportFormat;

/**
 * Generates filenames for exported files.
 *
 * Bind your own implementation in a service provider to customize
 * the naming strategy (e.g., include user ID, report type, etc.).
 */
interface FilenameGenerator
{
    /**
     * Generate a filename for the exported file.
     *
     * @param  string|null  $customName  A user-provided name (without extension), or null for auto-generated.
     * @param  ExportFormat  $format  The export format, used for the file extension.
     * @return string  The complete filename with extension (e.g., "users_2024-01-15_143022.xlsx").
     */
    public function generate(?string $customName, ExportFormat $format): string;
}
