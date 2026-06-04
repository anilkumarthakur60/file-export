<?php

declare(strict_types=1);

namespace Anil\FileExport\DataTransferObjects;

/**
 * Immutable CSV/TSV writer settings.
 *
 * Maps to Maatwebsite's WithCustomCsvSettings. Ignored by non-delimited
 * formats (xlsx, ods, …). The most common need is `useBom: true` so Excel
 * opens UTF-8 CSV files with the correct encoding, and a `;` delimiter for
 * locales where Excel expects semicolons.
 */
final readonly class CsvSettings
{
    /**
     * @param  string  $delimiter  Field delimiter (e.g. "," or ";").
     * @param  string  $enclosure  Field enclosure character.
     * @param  string  $lineEnding  Line ending sequence.
     * @param  bool  $useBom  Prepend a UTF-8 byte-order mark (helps Excel detect UTF-8).
     * @param  bool  $includeSeparatorLine  Emit a leading "sep=" hint line.
     * @param  bool  $excelCompatibility  Enable Maatwebsite's Excel-compatibility mode.
     * @param  string  $outputEncoding  Output encoding (empty = no conversion).
     */
    public function __construct(
        public string $delimiter = ',',
        public string $enclosure = '"',
        public string $lineEnding = "\n",
        public bool $useBom = false,
        public bool $includeSeparatorLine = false,
        public bool $excelCompatibility = false,
        public string $outputEncoding = '',
    ) {}

    /**
     * Create from a config array (typically config/file-export.php → csv).
     *
     * @param  array<string, mixed>  $values
     */
    public static function fromArray(array $values): self
    {
        return new self(
            delimiter: is_string($values['delimiter'] ?? null) ? $values['delimiter'] : ',',
            enclosure: is_string($values['enclosure'] ?? null) ? $values['enclosure'] : '"',
            lineEnding: is_string($values['line_ending'] ?? null) ? $values['line_ending'] : "\n",
            useBom: is_bool($values['use_bom'] ?? null) ? $values['use_bom'] : false,
            includeSeparatorLine: is_bool($values['include_separator_line'] ?? null) ? $values['include_separator_line'] : false,
            excelCompatibility: is_bool($values['excel_compatibility'] ?? null) ? $values['excel_compatibility'] : false,
            outputEncoding: is_string($values['output_encoding'] ?? null) ? $values['output_encoding'] : '',
        );
    }

    /**
     * Convert to the array shape Maatwebsite's getCsvSettings() expects.
     *
     * @return array<string, string|bool>
     */
    public function toArray(): array
    {
        return [
            'delimiter' => $this->delimiter,
            'enclosure' => $this->enclosure,
            'line_ending' => $this->lineEnding,
            'use_bom' => $this->useBom,
            'include_separator_line' => $this->includeSeparatorLine,
            'excel_compatibility' => $this->excelCompatibility,
            'output_encoding' => $this->outputEncoding,
        ];
    }
}
