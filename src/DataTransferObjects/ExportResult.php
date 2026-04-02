<?php

declare(strict_types=1);

namespace Anil\FileExport\DataTransferObjects;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Immutable result returned by store() and queue() operations.
 *
 * Contains the generated filename, download URL (if available), storage
 * path, and disk name. Convert to a JSON response with toResponse()
 * or access individual properties directly.
 */
final readonly class ExportResult
{
    /**
     * @param  string  $filename  The generated filename (e.g., "users_2024-01-15_143022.xlsx").
     * @param  string|null  $url  The download URL. Null for queued exports (file may not exist yet).
     * @param  string|null  $path  The file path relative to disk root. Null when not stored.
     * @param  string|null  $disk  The filesystem disk name. Null when not stored.
     */
    public function __construct(
        public string $filename,
        public ?string $url = null,
        public ?string $path = null,
        public ?string $disk = null,
    ) {}

    /**
     * Convert the result into a JSON response suitable for API endpoints.
     */
    public function toResponse(int $statusCode = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse($this->toArray(), $statusCode);
    }

    /**
     * Convert the result into a plain array.
     *
     * @return array{success: true, filename: string, url: string|null, path: string|null, disk: string|null}
     */
    public function toArray(): array
    {
        return [
            'success' => true,
            'filename' => $this->filename,
            'url' => $this->url,
            'path' => $this->path,
            'disk' => $this->disk,
        ];
    }
}
