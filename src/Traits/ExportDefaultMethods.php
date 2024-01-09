<?php

namespace Anil\FileExport\Traits;

use Anil\FileExport\Service\CommonExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use ReflectionException;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

trait ExportDefaultMethods
{
    private function finalExport(string $exportName, $exportClass): JsonResponse
    {
        $path = 'export/'.$exportName;
        $export = Excel::store($exportClass, $path, config('filesystems.default'));

        if ($export) {
            return response()->json([
                'code'    => 200,
                'url'     => Storage::disk('public')->url($path),
                'message' => 'Success',
            ], ResponseAlias::HTTP_OK);
        }

        return response()->json([
            'code'    => 500,
            'message' => 'Something went wrong',
        ], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function commonExport($model, $header, $mapping): CommonExport
    {
        return new CommonExport($model, $header, $mapping);
    }

    private function paginatedData($model)
    {
        request()->validate([
            'exportRows' => 'nullable|numeric|lte:100000000|gte:0',
        ]);

        return $model->paginate(request()->query('exportRows', $model->count()));
    }

    /**
     * @throws ReflectionException
     */
    private function exportName($modelClass): string
    {
        if (request()->filled('exportName')) {
            return request()->input('exportName').time().'.xlsx';
        }

        $reflection = new \ReflectionClass(app($modelClass));
        $className = $reflection->getShortName();

        return Str::slug($className).time().'.xlsx';
    }

    /**
     * @throws ReflectionException
     */
    public function exportData($builder, $header, $mapping): JsonResponse
    {
        $exportClass = $this->commonExport(
            model: $this->paginatedData(model: $builder),
            header: $header,
            mapping: $mapping,
        );

        $exportName = $this->exportName(modelClass: get_class($builder->getModel()));

        return $this->finalExport(exportName: $exportName, exportClass: $exportClass);
    }
}
