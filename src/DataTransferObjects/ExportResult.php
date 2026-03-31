<?php

declare(strict_types=1);

namespace Anil\FileExport\DataTransferObjects;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class ExportResult
{
    public function __construct(
        public string $filename,
        public ?string $url = null,
        public ?string $path = null,
        public ?string $disk = null,
    ) {}

    public function toResponse(int $status = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse($this->toArray(), $status);
    }

    /**
     * @return array{success: true, filename: string, url: string|null, path: string|null, disk: string|null, message: string}
     */
    public function toArray(): array
    {
        return [
            'success' => true,
            'filename' => $this->filename,
            'url' => $this->url,
            'path' => $this->path,
            'disk' => $this->disk,
            'message' => 'File exported successfully.',
        ];
    }
}
