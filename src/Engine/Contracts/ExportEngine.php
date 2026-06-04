<?php

declare(strict_types=1);

namespace Anil\FileExport\Engine\Contracts;

use Anil\FileExport\Engine\ExportDocument;
use Anil\FileExport\Enums\ExportFormat;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Renders an ExportDocument to a given format and delivers it.
 *
 * The package's own replacement for the third-party spreadsheet facade —
 * bind your own implementation to plug in a different rendering backend.
 */
interface ExportEngine
{
    /**
     * Render the document and write it to the given disk/path.
     *
     * @return bool  True on success.
     */
    public function store(ExportDocument $document, string $disk, string $path, ExportFormat $format): bool;

    /**
     * Render the document and return it as a streamed browser download.
     */
    public function download(ExportDocument $document, string $filename, ExportFormat $format): BinaryFileResponse;

    /**
     * Render the document and return its raw bytes.
     */
    public function raw(ExportDocument $document, ExportFormat $format): string;
}
