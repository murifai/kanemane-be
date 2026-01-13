<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class BudgetController extends Controller
{
    /**
     * Get the budget for a specific month and currency.
     * If no budget is set for the month, it falls back to the previous set budget.
     */
    public function show(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'currency' => 'required|in:JPY,IDR',
            'month' => 'nullable|date_format:Y-m',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $currency = $request->currency;
        $month = $request->month 
            ? Carbon::createFromFormat('Y-m', $request->month)->startOfMonth() 
            : now()->startOfMonth();

        // 1. Check for specific month budget
        $budget = Budget::where('user_id', $user->id)
            ->where('currency', $currency)
            ->where('month', $month->format('Y-m-d'))
            ->first();

        if ($budget) {
            return response()->json([
                'amount' => (float)$budget->amount,
                'currency' => $currency,
                'month' => $month->format('Y-m'),
                'is_fallback' => false,
            ]);
        }

        // 2. Fallback to latest previous budget
        $fallback = Budget::where('user_id', $user->id)
            ->where('currency', $currency)
            ->where('month', '<', $month->format('Y-m-d'))
            ->orderBy('month', 'desc')
            ->first();

        return response()->json([
            'amount' => (float)($fallback ? $fallback->amount : 0),
            'currency' => $currency,
            'month' => $month->format('Y-m'),
            'is_fallback' => true,
            'source_month' => $fallback ? $fallback->month->format('Y-m') : null,
        ]);
    }

    /**
     * Set a budget for a specific month.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'currency' => 'required|in:JPY,IDR',
            'amount' => 'required|numeric|min:0',
            'month' => 'required|date_format:Y-m',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $month = Carbon::createFromFormat('Y-m', $request->month)->startOfMonth();

        $budget = Budget::updateOrCreate(
            [
                'user_id' => $user->id,
                'currency' => $request->currency,
                'month' => $month->format('Y-m-d'),
            ],
            [
                'amount' => $request->amount,
            ]
        );

        return response()->json([
            'amount' => (float)$budget->amount,
            'currency' => $budget->currency,
            'month' => $budget->month->format('Y-m'),
        ]);
    }
}
