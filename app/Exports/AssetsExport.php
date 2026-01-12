<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Illuminate\Support\Collection;

class AssetsExport implements FromCollection, WithHeadings, WithTitle, WithStyles, WithColumnWidths
{
    protected $assets;

    public function __construct($assets)
    {
        $this->assets = $assets;
    }

    /**
     * @return Collection
     */
    public function collection()
    {
        return collect($this->assets)->map(function ($asset) {
            return [
                $asset['name'],
                $asset['type'],
                $asset['country'],
                $asset['currency'],
                number_format($asset['start_balance'], 2, ',', '.'),
                number_format($asset['end_balance'], 2, ',', '.'),
                number_format($asset['total_change'], 2, ',', '.'),
                number_format($asset['daily_change'], 2, ',', '.'),
                $asset['days'] . ' hari',
            ];
        });
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Nama Aset',
            'Tipe',
            'Negara',
            'Mata Uang',
            'Saldo Awal',
            'Saldo Akhir',
            'Perubahan Total',
            'Perubahan Per Hari',
            'Periode',
        ];
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'Aset';
    }

    /**
     * @return array
     */
    public function columnWidths(): array
    {
        return [
            'A' => 25,
            'B' => 15,
            'C' => 12,
            'D' => 12,
            'E' => 15,
            'F' => 15,
            'G' => 18,
            'H' => 18,
            'I' => 12,
        ];
    }

    /**
     * @param Worksheet $sheet
     */
    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();

        // Header style
        $sheet->getStyle('A1:I1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E0E0E0'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Data borders
        if ($lastRow > 1) {
            $sheet->getStyle('A2:I' . $lastRow)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC'],
                    ],
                ],
            ]);
        }

        return [];
    }
}
