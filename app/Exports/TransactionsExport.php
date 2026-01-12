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

class TransactionsExport implements FromCollection, WithHeadings, WithTitle, WithStyles, WithColumnWidths
{
    protected $transactions;
    protected $totals;
    protected $currency;

    public function __construct($transactions, $totals, $currency)
    {
        $this->transactions = $transactions;
        $this->totals = $totals;
        $this->currency = $currency;
    }

    /**
     * @return Collection
     */
    public function collection()
    {
        $data = collect($this->transactions)->map(function ($transaction) {
            return [
                $transaction['date'],
                $transaction['type'],
                $transaction['category'],
                $transaction['asset'],
                number_format($transaction['amount'], 2, ',', '.'),
                $transaction['currency'],
                $transaction['note'],
            ];
        });

        // Add empty row
        $data->push(['', '', '', '', '', '', '']);

        // Add totals
        $data->push([
            '',
            '',
            '',
            'Total Pemasukan',
            number_format($this->totals['total_income'], 2, ',', '.'),
            $this->currency,
            '',
        ]);

        $data->push([
            '',
            '',
            '',
            'Total Pengeluaran',
            number_format($this->totals['total_expense'], 2, ',', '.'),
            $this->currency,
            '',
        ]);

        $data->push([
            '',
            '',
            '',
            'Selisih',
            number_format($this->totals['balance'], 2, ',', '.'),
            $this->currency,
            '',
        ]);

        return $data;
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Tanggal',
            'Tipe',
            'Kategori',
            'Aset',
            'Jumlah',
            'Mata Uang',
            'Catatan',
        ];
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'Transaksi';
    }

    /**
     * @return array
     */
    public function columnWidths(): array
    {
        return [
            'A' => 12,
            'B' => 15,
            'C' => 20,
            'D' => 25,
            'E' => 15,
            'F' => 12,
            'G' => 30,
        ];
    }

    /**
     * @param Worksheet $sheet
     */
    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $totalStartRow = $lastRow - 2;

        // Header style
        $sheet->getStyle('A1:G1')->applyFromArray([
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
            $sheet->getStyle('A2:G' . ($totalStartRow - 1))->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC'],
                    ],
                ],
            ]);
        }

        // Totals style
        $sheet->getStyle('D' . $totalStartRow . ':F' . $lastRow)->applyFromArray([
            'font' => [
                'bold' => true,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FFF9C4'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ]);

        return [];
    }
}
