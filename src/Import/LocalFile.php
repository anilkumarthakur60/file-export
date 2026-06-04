<?php

declare(strict_types=1);

namespace Anil\FileExport\Import;

use Anil\FileExport\Exceptions\FileImportException;
use Illuminate\Filesystem\FilesystemManager;

/**
 * Resolves an import source to a readable local path, copying remote-disk
 * files to a temp file when necessary.
 */
final class LocalFile
{
    /**
     * @return array{0: string, 1: bool}  [localPath, isTemporary]
     *
     * @throws FileImportException
     */
    public static function resolve(FilesystemManager $filesystem, string $file, ?string $disk, string $defaultDisk): array
    {
        if ($disk === null && is_file($file)) {
            return [$file, false];
        }

        $storage = $filesystem->disk($disk ?? $defaultDisk);

        if (! $storage->exists($file)) {
            throw FileImportException::unreadableFile($file);
        }

        try {
            $local = $storage->path($file);
            if (is_file($local)) {
                return [$local, false];
            }
        } catch (\Throwable) {
            // No local path (e.g. S3) — copy to a temp file below.
        }

        $extension = pathinfo($file, PATHINFO_EXTENSION) ?: 'tmp';
        $temporary = sys_get_temp_dir().'/'.uniqid('fimp_', true).'.'.$extension;

        $stream = $storage->readStream($file);
        if ($stream === null) {
            throw FileImportException::unreadableFile($file);
        }

        file_put_contents($temporary, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        return [$temporary, true];
    }
}
