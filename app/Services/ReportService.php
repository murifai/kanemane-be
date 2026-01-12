<?php

namespace App\Services;

use App\Models\Income;
use App\Models\Expense;
use App\Models\Asset;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportService
{
    /**
     * Generate report data for a given user, date range, and currency
     */
    public function generateReport($userId, $startDate, $endDate, $currency = null)
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        // Get transactions (incomes and expenses)
        $transactions = $this->getTransactions($userId, $start, $end, $currency);
        
        // Get asset snapshots
        $assets = $this->getAssetSnapshots($userId, $start, $end, $currency);
        
        // Calculate totals
        $totals = $this->calculateTotals($transactions);

        return [
            'transactions' => $transactions,
            'assets' => $assets,
            'totals' => $totals,
            'period' => [
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
            ],
            'currency' => $currency ?? 'ALL',
        ];
    }

    /**
     * Get all transactions (incomes and expenses) for the period
     */
    private function getTransactions($userId, $startDate, $endDate, $currency)
    {
        // Get incomes
        $incomesQuery = Income::where('owner_type', 'App\\Models\\User')
            ->where('owner_id', $userId)
            ->whereBetween('date', [$startDate, $endDate])
            ->with('asset');

        if ($currency) {
            $incomesQuery->where('currency', $currency);
        }

        $incomes = $incomesQuery->get()->map(function ($income) {
            return [
                'date' => $income->date->format('Y-m-d'),
                'type' => 'Pemasukan',
                'category' => $income->category,
                'asset' => $income->asset ? $income->asset->name : '-',
                'amount' => $income->amount,
                'currency' => $income->currency,
                'note' => $income->note ?? '-',
            ];
        });

        // Get expenses
        $expensesQuery = Expense::where('owner_type', 'App\\Models\\User')
            ->where('owner_id', $userId)
            ->whereBetween('date', [$startDate, $endDate])
            ->with('asset');

        if ($currency) {
            $expensesQuery->where('currency', $currency);
        }

        $expenses = $expensesQuery->get()->map(function ($expense) {
            return [
                'date' => $expense->date->format('Y-m-d'),
                'type' => 'Pengeluaran',
                'category' => $expense->category,
                'asset' => $expense->asset ? $expense->asset->name : '-',
                'amount' => $expense->amount,
                'currency' => $expense->currency,
                'note' => $expense->note ?? '-',
            ];
        });

        // Merge and sort by date
        return $incomes->concat($expenses)->sortBy('date')->values();
    }

    /**
     * Calculate totals for incomes and expenses
     */
    private function calculateTotals($transactions)
    {
        $totalIncome = 0;
        $totalExpense = 0;

        foreach ($transactions as $transaction) {
            if ($transaction['type'] === 'Pemasukan') {
                $totalIncome += $transaction['amount'];
            } else {
                $totalExpense += $transaction['amount'];
            }
        }

        return [
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'balance' => $totalIncome - $totalExpense,
        ];
    }

    /**
     * Get asset snapshots with daily changes
     */
    private function getAssetSnapshots($userId, $startDate, $endDate, $currency)
    {
        $assetsQuery = Asset::where('owner_type', 'App\\Models\\User')
            ->where('owner_id', $userId);

        if ($currency) {
            $assetsQuery->where('currency', $currency);
        }

        $assets = $assetsQuery->get();

        return $assets->map(function ($asset) use ($startDate, $endDate) {
            // Calculate balance at start of period
            $startBalance = $this->calculateAssetBalanceAtDate($asset, $startDate);
            
            // Current balance (end of period)
            $endBalance = $asset->balance;
            
            // Calculate number of days in period
            $start = Carbon::parse($startDate);
            $end = Carbon::parse($endDate);
            $days = $start->diffInDays($end);
            
            // Calculate daily change (avoid division by zero)
            $totalChange = $endBalance - $startBalance;
            $dailyChange = $days > 0 ? $totalChange / $days : 0;

            return [
                'name' => $asset->name,
                'type' => $asset->type,
                'country' => $asset->country,
                'currency' => $asset->currency,
                'start_balance' => $startBalance,
                'end_balance' => $endBalance,
                'total_change' => $totalChange,
                'daily_change' => $dailyChange,
                'days' => $days,
            ];
        });
    }

    /**
     * Calculate asset balance at a specific date
     */
    private function calculateAssetBalanceAtDate($asset, $date)
    {
        // Get current balance
        $currentBalance = $asset->balance;

        // Get all transactions after the specified date
        $incomesAfter = Income::where('asset_id', $asset->id)
            ->where('date', '>', $date)
            ->sum('amount');

        $expensesAfter = Expense::where('asset_id', $asset->id)
            ->where('date', '>', $date)
            ->sum('amount');

        // Subtract transactions that happened after the date
        return $currentBalance - $incomesAfter + $expensesAfter;
    }
}
