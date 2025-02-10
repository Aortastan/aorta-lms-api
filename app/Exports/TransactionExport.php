<?php

namespace App\Exports;

use App\Models\Transaction;
use App\Models\Package;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Font;
use Carbon\Carbon;

class TransactionExport implements FromCollection, WithHeadings, WithStyles
{
    /**
     * @return \Illuminate\Support\Collection
     */

    protected $startDate;
    protected $endDate;
    protected $cleanedPackage;

    public function __construct($startDate = null, $endDate = null, $cleanedPackage = null)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->cleanedPackage = $cleanedPackage;
    }

    public function collection()
    {


        $query = Transaction::with(['detailTransaction', 'detailTransaction.package', 'user', 'payment']);

        // Apply date filtering if provided
        if ($this->startDate) {
            $query->whereDate('created_at', '>=', $this->startDate);
        }
        if ($this->endDate) {
            $query->whereDate('created_at', '<=', $this->endDate);
        }


        if ($this->cleanedPackage) {
            // Get the UUIDs of packages that match the cleaned package name
            $uuids = Package::where('name', 'like', "%{$this->cleanedPackage}%")->pluck('uuid');

            // Check if any UUIDs were found
            if ($uuids->isNotEmpty()) {
                // Apply the whereHas condition to filter transactions
                $query->whereHas('detailTransaction', function ($q) use ($uuids) {
                    $q->whereIn('package_uuid', $uuids);
                });
            }
        }
        $detailRab = $query->get();

        $numberedData = new Collection();

        foreach ($detailRab as $key => $item) {
            $packages = [];
            $formattedPackages = [];
            foreach ($item->detailTransaction as $index1 => $detail) {
                $packages[] = [
                    "name" => isset($detail->package) ? $detail->package->name : 'No Package',  // Cek apakah package ada
                    "type_of_purchase" => $detail->type_of_purchase ?? 'N/A',  // Cek apakah type_of_purchase ada
                    "transaction_type" => $detail->transaction_type ?? 'N/A',  // Cek apakah transaction_type ada
                    "price" => 'Rp ' . number_format($detail->detail_amount, 0, ',', '.')
                ];
            }
            foreach ($packages as $package) {
                // $formattedPackages[] = "Name: {$package['name']}, Price: {$package['price']}, Type of Purchase: {$package['type_of_purchase']}, Type of Transaction: {$package['transaction_type']}";
                $formattedPackages[] = "{$package['name']}";
            }

            // Check the contents before imploding
            $packagesString = !empty($formattedPackages) ? implode(PHP_EOL, $formattedPackages) : "No packages available";
            $numberedData->push([
                'No.' => $key + 1,
                'Username' => $item->user->name ?? 'N/A',
                'Mobile Number' => $item->user->mobile_number ?? 'N/A', // Prepend apostrophe
                'amount' => 'Rp ' . number_format($item->transaction_amount, 0, ',', '.'),
                'status' => $item->transaction_status,
                // 'payment' => $item->payment->gateway_name,
                'url' => $item->url,
                'Packages' => $packagesString,
                "expired_date" => Carbon::parse($item->expiry_date)->format('d-m-Y H:i:s'), // Format here
                "created_at" => Carbon::parse($item->created_at)->format('d-m-Y H:i:s'), // Format here
                "updated_at" => Carbon::parse($item->updated_at)->format('d-m-Y H:i:s'), // Format here if needed
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
            'Username',
            'Mobile Number',
            'Amount',
            'Status',
            // 'Payment Method',
            'URL',
            'Packages',
            'Expired At',
            'Created At',
            'Updated At'
        ];
    }

    /**
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
     *
     * @return array
     */


    public function styles(Worksheet $sheet): array
    {
        // Mengatur lebar kolom
        $sheet->getColumnDimension('A')->setWidth(5); // Lebar kolom A
        $sheet->getColumnDimension('B')->setWidth(30); // Lebar kolom B
        $sheet->getColumnDimension('C')->setWidth(15); // Lebar kolom C
        $sheet->getColumnDimension('D')->setWidth(15); // Lebar kolom D
        $sheet->getColumnDimension('E')->setWidth(10); // Lebar kolom E
        $sheet->getColumnDimension('F')->setWidth(20); // Lebar kolom F
        $sheet->getColumnDimension('G')->setWidth(25); // Lebar kolom G
        $sheet->getColumnDimension('H')->setWidth(40); // Lebar kolom H
        $sheet->getColumnDimension('I')->setWidth(20); // Lebar kolom I
        $sheet->getColumnDimension('J')->setWidth(20); // Lebar kolom J
        // $sheet->getColumnDimension('K')->setWidth(20); // Lebar kolom K
        // Mengatur border untuk semua sel
        $styleArray = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN, // Mengatur jenis garis
                    'color' => ['argb' => 'FF000000'], // Mengatur warna garis (hitam)
                ],
            ],
        ];

        // Menerapkan gaya border ke seluruh area data
        $sheet->getStyle('A1:J' . ($sheet->getHighestRow()))->applyFromArray($styleArray);


        $headerStyle = [
            'font' => [
                'bold' => true, // Menebalkan teks
                'color' => [
                    'argb' => Color::COLOR_WHITE, // Mengatur warna teks (putih)
                ],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => [
                    'argb' => '0066CC', // Mengatur warna latar belakang (biru)
                ],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, // Menyusun teks di tengah
            ],
        ];

        // Menerapkan gaya header ke baris pertama
        $sheet->getStyle('A1:J1')->applyFromArray($headerStyle);
        return [
            // Pengaturan lainnya
            'A:Z' => [
                'alignment' => ['wrapText' => true],
            ],
        ];
    }
}
