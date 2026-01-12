<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use App\Exports\FinancialReportExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class ReportController extends Controller
{
    protected $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Export financial report
     */
    public function export(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'currency' => 'required|in:JPY,IDR',
            'format' => 'nullable|in:xlsx,csv',
        ]);

        $userId = $request->user()->id;
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $currency = $request->input('currency');
        $format = $request->input('format', 'xlsx');

        // Generate report data
        $reportData = $this->reportService->generateReport(
            $userId,
            $startDate,
            $endDate,
            $currency
        );

        // Generate filename
        $currencyLabel = $currency ?? 'ALL';
        $start = Carbon::parse($startDate)->format('Ymd');
        $end = Carbon::parse($endDate)->format('Ymd');
        $filename = "laporan_{$currencyLabel}_{$start}_to_{$end}.{$format}";

        // Export to Excel/CSV
        if ($format === 'csv') {
            // For CSV, we'll export only the transactions sheet
            return Excel::download(
                new \App\Exports\TransactionsExport(
                    $reportData['transactions'],
                    $reportData['totals'],
                    $reportData['currency']
                ),
                $filename,
                \Maatwebsite\Excel\Excel::CSV
            );
        }

        // Default: Excel with multiple sheets
        return Excel::download(
            new FinancialReportExport($reportData),
            $filename
        );
    }
}
