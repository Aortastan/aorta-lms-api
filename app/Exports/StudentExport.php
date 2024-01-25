<?php

namespace App\Exports;

use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StudentExport implements FromCollection, WithHeadings, WithStyles
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $detailRab = User::
            where('role', "student")
            ->get(['name', 'email', 'mobile_number']);

        $numberedData = new Collection();

        foreach ($detailRab as $key => $item) {
            $numberedData->push([
                'No.' => $key + 1,
                'Nama' => $item->name,
                'Email' => $item->email,
                'No HP' => $item->mobile_number,
            ]);
        }

        return $numberedData;
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'No.',
            'Nama',
            'Email',
            'No HP',
        ];
    }

    /**
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
     *
     * @return array
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            // Kolom A sampai Z akan memiliki word-wrap
            'A:Z' => [
                'alignment' => ['wrapText' => true],
            ],
        ];
    }
}
