<?php

namespace Anil\FileExport\Service;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;

class CommonExport implements FromCollection, WithMapping, WithHeadings, WithEvents
{
    public function __construct(public $model, public $headers, public $mapping)
    {
    }

    public function headings(): array
    {
        return $this->headers;
    }

    public function collection(): Collection
    {
        return $this->model->map(function ($item, $index) {
            $item->s_no = $index + 1;

            return $item;
        });
    }

    public function map($row): array
    {
        return call_user_func($this->mapping, $row);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $lastRow = $event->sheet->getDelegate()->getHighestRow();
                $event->sheet->getDelegate()->getStyle('A1:Z'.$lastRow)->getAlignment()->setWrapText(true);
            },
        ];
    }
}
