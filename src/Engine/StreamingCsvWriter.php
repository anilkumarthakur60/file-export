<?php

declare(strict_types=1);

namespace Anil\FileExport\Engine;

use Anil\FileExport\DataTransferObjects\CsvSettings;
use Anil\FileExport\Engine\Contracts\ExportSheet;
use Anil\FileExport\Enums\ExportFormat;
use Anil\FileExport\Exceptions\FileExportException;

/**
 * Streams a sheet to a delimited-text file (CSV/TSV) with O(1) memory.
 *
 * Rows are written to the file handle as they are produced by the sheet's
 * row generator, so a 10M-row export never materializes in memory. Fields are
 * always enclosed and the enclosure character is escaped by doubling, matching
 * common spreadsheet expectations.
 */
final class StreamingCsvWriter
{
    /**
     * Write the (first) sheet of a document to the given path.
     *
     * @throws FileExportException
     */
    public function write(ExportSheet $sheet, string $path, ExportFormat $format): void
    {
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw FileExportException::storageFailed($path);
        }

        $settings = $sheet->csvSettings();
        $delimiter = $format === ExportFormat::TSV ? "\t" : $settings->delimiter;

        try {
            if ($settings->useBom) {
                fwrite($handle, "\xEF\xBB\xBF");
            }

            if ($settings->includeSeparatorLine) {
                fwrite($handle, 'sep='.$delimiter.$settings->lineEnding);
            }

            $this->writeLine($handle, $sheet->headings(), $delimiter, $settings);

            foreach ($sheet->rows() as $row) {
                $this->writeLine($handle, $row, $delimiter, $settings);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  resource  $handle
     * @param  array<int, mixed>  $fields
     */
    private function writeLine($handle, array $fields, string $delimiter, CsvSettings $settings): void
    {
        $enclosure = $settings->enclosure;

        $cells = array_map(function ($field) use ($enclosure): string {
            $value = $this->stringify($field);

            return $enclosure.str_replace($enclosure, $enclosure.$enclosure, $value).$enclosure;
        }, $fields);

        $line = implode($delimiter, $cells).$settings->lineEnding;

        if ($settings->outputEncoding !== '') {
            $converted = mb_convert_encoding($line, $settings->outputEncoding, 'UTF-8');
            $line = $converted === false ? $line : $converted;
        }

        fwrite($handle, $line);
    }

    private function stringify(mixed $field): string
    {
        if ($field === null) {
            return '';
        }

        if (is_bool($field)) {
            return $field ? '1' : '0';
        }

        if (is_scalar($field)) {
            return (string) $field;
        }

        if ($field instanceof \Stringable || (is_object($field) && method_exists($field, '__toString'))) {
            return (string) $field;
        }

        if ($field instanceof \DateTimeInterface) {
            return $field->format('Y-m-d H:i:s');
        }

        $encoded = json_encode($field);

        return $encoded === false ? '' : $encoded;
    }
}
