<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Income;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get dashboard summary
     */
    public function summary(Request $request)
    {
        $user = $request->user();
        
        // Get all user's assets (personal + family)
        $personalAssets = $user->personalAssets;
        $familyAssets = collect();
        foreach ($user->families as $family) {
            $familyAssets = $familyAssets->merge($family->assets);
        }
        $allAssets = $personalAssets->merge($familyAssets);
        
        // Calculate totals by currency
        $totalJPY = $allAssets->where('currency', 'JPY')->sum('balance');
        $totalIDR = $allAssets->where('currency', 'IDR')->sum('balance');
        
        // Get current month transactions
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();
        
        $monthlyIncome = $user->incomes()
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->sum('amount');
            
        $monthlyExpense = $user->expenses()
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->sum('amount');
        
        // Get top expense category
        $topCategory = $user->expenses()
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->select('category', DB::raw('SUM(amount) as total'))
            ->groupBy('category')
            ->orderByDesc('total')
            ->first();

        // Get recent transactions (limit 10)
        $incomes = $user->incomes()->with('asset')->latest('date')->limit(10)->get()->map(function ($item) {
            $data = $item->toArray();
            $data['type'] = 'income';
            return $data;
        });
        $expenses = $user->expenses()->with('asset')->latest('date')->limit(10)->get()->map(function ($item) {
            $data = $item->toArray();
            $data['type'] = 'expense';
            return $data;
        });

        $recentTransactions = $incomes->concat($expenses)->sortByDesc('date')->take(10)->values();
        
        return response()->json([
            'user_name' => $user->name,
            'total_assets_jpy' => $totalJPY,
            'total_assets_idr' => $totalIDR,
            'monthly_income' => $monthlyIncome,
            'monthly_expense' => $monthlyExpense,
            'balance' => $monthlyIncome - $monthlyExpense,
            'top_expense_category' => $topCategory->category ?? null,
            'recent_transactions' => $recentTransactions,
            'goals' => [] 
        ]);
    }

    /**
     * Get chart data
     */
    public function charts(Request $request)
    {
        $user = $request->user();
        
        // Monthly trend (last 6 months)
        $monthlyTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $startOfMonth = $month->copy()->startOfMonth();
            $endOfMonth = $month->copy()->endOfMonth();
            
            $income = $user->incomes()
                ->whereBetween('date', [$startOfMonth, $endOfMonth])
                ->sum('amount');
                
            $expense = $user->expenses()
                ->whereBetween('date', [$startOfMonth, $endOfMonth])
                ->sum('amount');
            
            $monthlyTrend[] = [
                'month' => $month->format('Y-m'),
                'income' => $income,
                'expense' => $expense,
            ];
        }
        
        // Category breakdown (current month)
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();
        
        $categoryBreakdown = $user->expenses()
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->select('category', DB::raw('SUM(amount) as amount'))
            ->groupBy('category')
            ->get()
            ->map(function ($item) {
                return [
                    'category' => $item->category,
                    'amount' => (float)$item->amount,
                ];
            });
        
        return response()->json([
            'monthly_trend' => $monthlyTrend,
            'category_breakdown' => $categoryBreakdown,
        ]);
    }
}
