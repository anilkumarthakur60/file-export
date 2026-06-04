<?php

declare(strict_types=1);

namespace Anil\FileExport\Import;

use Anil\FileExport\Import\Contracts\BatchesInserts;
use Anil\FileExport\Import\Contracts\MapsToModel;
use Anil\FileExport\Import\Contracts\SkipsDuplicates;
use Anil\FileExport\Import\Contracts\SkipsOnError;
use Anil\FileExport\Import\Contracts\SpecifiesUpsertColumns;
use Anil\FileExport\Import\Contracts\Upserts;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Persists rows mapped to models — buffering for batch inserts, and applying
 * upsert / insert-or-ignore semantics when the importable opts in.
 */
final class ModelImporter
{
    /** @var array<int, array<int|string, mixed>> */
    private array $buffer = [];

    public function __construct(
        private readonly MapsToModel $importable,
    ) {}

    /**
     * @param  array<int|string, mixed>  $row
     */
    public function add(array $row): void
    {
        $this->buffer[] = $row;

        if ($this->importable instanceof BatchesInserts && count($this->buffer) >= $this->importable->batchSize()) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        if ($this->buffer === []) {
            return;
        }

        $buffer = $this->buffer;
        $this->buffer = [];

        if ($this->importable instanceof BatchesInserts) {
            $this->massFlush($buffer);
        } else {
            $this->singleFlush($buffer);
        }
    }

    /**
     * @param  array<int, array<int|string, mixed>>  $buffer
     */
    private function massFlush(array $buffer): void
    {
        /** @var array<class-string, array{model: Model, rows: array<int, array<string, mixed>>}> $grouped */
        $grouped = [];

        foreach ($buffer as $row) {
            foreach ($this->toModels($row) as $model) {
                $class = $model::class;
                $grouped[$class] ??= ['model' => $model, 'rows' => []];
                $grouped[$class]['rows'][] = $model->getAttributes();
            }
        }

        foreach ($grouped as $group) {
            $this->guard(fn () => $this->writeBatch($group['model'], $group['rows']));
        }
    }

    /**
     * @param  array<int, array<int|string, mixed>>  $buffer
     */
    private function singleFlush(array $buffer): void
    {
        foreach ($buffer as $row) {
            $this->guard(function () use ($row): void {
                foreach ($this->toModels($row) as $model) {
                    if ($this->importable instanceof Upserts) {
                        $this->writeBatch($model, [$model->getAttributes()]);
                    } elseif ($this->importable instanceof SkipsDuplicates) {
                        $model->newQuery()->insertOrIgnore([$model->getAttributes()]);
                    } else {
                        $model->save();
                    }
                }
            });
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function writeBatch(Model $model, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        if ($this->importable instanceof Upserts) {
            $columns = $this->importable instanceof SpecifiesUpsertColumns ? $this->importable->upsertColumns() : null;
            $model->newQuery()->upsert($rows, $this->importable->uniqueBy(), $columns);

            return;
        }

        if ($this->importable instanceof SkipsDuplicates) {
            $model->newQuery()->insertOrIgnore($rows);

            return;
        }

        $model->newQuery()->insert($rows);
    }

    /**
     * @param  array<int|string, mixed>  $row
     * @return array<int, Model>
     */
    private function toModels(array $row): array
    {
        $result = $this->importable->model($row);

        if ($result === null) {
            return [];
        }

        return is_array($result) ? array_values($result) : [$result];
    }

    private function guard(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            if ($this->importable instanceof SkipsOnError) {
                $this->importable->onError($e);

                return;
            }

            throw $e;
        }
    }
}
