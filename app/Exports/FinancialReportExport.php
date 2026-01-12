<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class FinancialReportExport implements WithMultipleSheets
{
    protected $reportData;

    public function __construct($reportData)
    {
        $this->reportData = $reportData;
    }

    /**
     * @return array
     */
    public function sheets(): array
    {
        return [
            new TransactionsExport(
                $this->reportData['transactions'],
                $this->reportData['totals'],
                $this->reportData['currency']
            ),
            new AssetsExport($this->reportData['assets']),
        ];
    }
}
