<?php

declare(strict_types=1);

namespace Anil\FileExport\Contracts;

interface UrlResolverContract
{
    public function resolve(string $disk, string $path): string;
}
