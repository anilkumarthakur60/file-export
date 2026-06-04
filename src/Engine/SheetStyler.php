<?php

declare(strict_types=1);

namespace Anil\FileExport\Engine;

use Anil\FileExport\Engine\Contracts\ExportSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Applies a sheet's styling to a rendered PhpSpreadsheet worksheet.
 *
 * Owns all the spreadsheet-specific formatting (header font/fill/alignment,
 * frozen header, auto-filter, wrap text, column number formats, column widths,
 * auto-sizing) so the export classes stay free of rendering concerns.
 */
final class SheetStyler
{
    public function apply(Worksheet $worksheet, ExportSheet $sheet): void
    {
        $highestColumn = $worksheet->getHighestColumn();
        $highestRow = $worksheet->getHighestRow();
        $style = $sheet->styling();

        $this->styleHeader($worksheet, $style, $highestColumn);

        if ($style->freezeHeader) {
            $worksheet->freezePane('A2');
        }

        if ($sheet->applyFullRangeStyling()) {
            if ($style->wrapText) {
                $worksheet->getStyle("A1:{$highestColumn}{$highestRow}")
                    ->getAlignment()
                    ->setWrapText(true)
                    ->setVertical(Alignment::VERTICAL_TOP);
            }

            if ($style->autoFilter) {
                $worksheet->setAutoFilter("A1:{$highestColumn}1");
            }
        }

        $this->applyColumnFormats($worksheet, $sheet->columnFormats(), $highestRow);
        $this->applyColumnSizing($worksheet, $sheet, $highestColumn);
    }

    private function styleHeader(Worksheet $worksheet, \Anil\FileExport\DataTransferObjects\StyleConfig $style, string $highestColumn): void
    {
        $header = $worksheet->getStyle("A1:{$highestColumn}1");

        $font = $header->getFont();
        $font->setBold($style->headerBold);
        $font->setSize($style->headerFontSize);

        if ($style->headerFontColor !== '') {
            $font->getColor()->setARGB($this->argb($style->headerFontColor));
        }

        if ($style->headerBackground !== '') {
            $header->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB($this->argb($style->headerBackground));
        }

        $header->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
    }

    /**
     * @param  array<string, string>  $formats
     */
    private function applyColumnFormats(Worksheet $worksheet, array $formats, int $highestRow): void
    {
        foreach ($formats as $column => $format) {
            $worksheet->getStyle("{$column}2:{$column}{$highestRow}")
                ->getNumberFormat()
                ->setFormatCode($format);
        }
    }

    private function applyColumnSizing(Worksheet $worksheet, ExportSheet $sheet, string $highestColumn): void
    {
        if ($sheet->autoSize()) {
            $last = Coordinate::columnIndexFromString($highestColumn);
            for ($index = 1; $index <= $last; $index++) {
                $worksheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setAutoSize(true);
            }
        }

        // Explicit widths win over auto-size.
        foreach ($sheet->columnWidths() as $column => $width) {
            $worksheet->getColumnDimension($column)->setAutoSize(false);
            $worksheet->getColumnDimension($column)->setWidth((float) $width);
        }
    }

    /**
     * Normalize a hex colour to 8-digit ARGB (prepend opaque alpha if needed).
     */
    private function argb(string $color): string
    {
        return strlen($color) === 6 ? 'FF'.$color : $color;
    }
}
