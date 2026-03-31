<?php

declare(strict_types=1);

namespace Anil\FileExport\Contracts;

use Anil\FileExport\Enums\ExportFormat;

interface FilenameGeneratorContract
{
    public function generate(?string $customName, ExportFormat $format): string;
}
