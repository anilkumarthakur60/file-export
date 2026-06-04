<?php

declare(strict_types=1);

namespace Anil\FileExport\Import;

use Anil\FileExport\Import\Contracts\SkipsOnFailure;
use Anil\FileExport\Import\Contracts\ValidatesRows;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Validation\ValidationException;

/**
 * Validates import rows against an importable's rules.
 *
 * Returns true when the row is valid. On failure, either records a Failure and
 * returns false (when the importable skips on failure) or throws.
 */
final class RowValidator
{
    public function __construct(
        private readonly ValidationFactory $validator,
    ) {}

    /**
     * @param  array<int|string, mixed>  $row
     *
     * @throws ValidationException
     */
    public function passes(ValidatesRows $importable, array $row, int $rowNumber): bool
    {
        $validator = $this->validator->make($row, $importable->rules());

        if ($validator->passes()) {
            return true;
        }

        if ($importable instanceof SkipsOnFailure) {
            /** @var array<int, string> $messages */
            $messages = $validator->errors()->all();
            $importable->onFailure([new Failure($rowNumber, $messages, $row)]);

            return false;
        }

        throw new ValidationException($validator);
    }
}
