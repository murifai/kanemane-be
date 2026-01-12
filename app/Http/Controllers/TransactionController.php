<?php

namespace App\Http\Controllers;

use App\Models\Income;
use App\Models\Expense;
use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    /**
     * Display a listing of transactions
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Get personal transactions
        $incomes = $user->incomes()->with('asset')->latest('date')->get()->map(function ($income) {
            $income->type = 'income';
            return $income;
        });
        
        $expenses = $user->expenses()->with('asset')->latest('date')->get()->map(function ($expense) {
            $expense->type = 'expense';
            return $expense;
        });
        
        // Merge and sort
        $transactions = $incomes->concat($expenses)->sortByDesc('date')->values();
        
        return response()->json($transactions);
    }

    /**
     * Store a new income
     */
    public function storeIncome(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'asset_id' => 'required|exists:assets,id',
            'category' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'date' => 'required|date',
            'note' => 'nullable|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $user = $request->user();
        $asset = Asset::findOrFail($request->asset_id);
        
        DB::beginTransaction();
        try {
            // Create income
            $income = Income::create([
                'owner_type' => $asset->owner_type,
                'owner_id' => $asset->owner_id,
                'asset_id' => $asset->id,
                'category' => $request->category,
                'amount' => $request->amount,
                'currency' => $asset->currency,
                'date' => $request->date,
                'note' => $request->note,
                'created_by' => $user->id,
            ]);
            
            // Update asset balance
            $asset->increment('balance', $request->amount);
            
            DB::commit();
            
            return response()->json($income->load('asset'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create income'], 500);
        }
    }

    /**
     * Store a new expense
     */
    public function storeExpense(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'asset_id' => 'required|exists:assets,id',
            'category' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'date' => 'required|date',
            'note' => 'nullable|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $user = $request->user();
        $asset = Asset::findOrFail($request->asset_id);
        
        // Check if sufficient balance
        if ($asset->balance < $request->amount) {
            return response()->json(['error' => 'Insufficient balance'], 400);
        }
        
        DB::beginTransaction();
        try {
            // Create expense
            $expense = Expense::create([
                'owner_type' => $asset->owner_type,
                'owner_id' => $asset->owner_id,
                'asset_id' => $asset->id,
                'category' => $request->category,
                'amount' => $request->amount,
                'currency' => $asset->currency,
                'date' => $request->date,
                'note' => $request->note,
                'created_by' => $user->id,
            ]);
            
            // Update asset balance
            $asset->decrement('balance', $request->amount);
            
            DB::commit();
            
            return response()->json($expense->load('asset'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create expense'], 500);
        }
    }

    /**
     * Display the specified transaction
     */
    public function show(Request $request, string $id)
    {
        // Try to find in incomes first, then expenses
        $transaction = Income::find($id);
        if (!$transaction) {
            $transaction = Expense::find($id);
        }
        
        if (!$transaction) {
            return response()->json(['error' => 'Transaction not found'], 404);
        }
        
        return response()->json($transaction->load('asset'));
    }

    /**
     * Remove the specified transaction
     */
    public function destroy(Request $request, string $id)
    {
        // Try to find in incomes first
        $transaction = Income::find($id);
        $type = 'income';
        
        if (!$transaction) {
            $transaction = Expense::find($id);
            $type = 'expense';
        }
        
        if (!$transaction) {
            return response()->json(['error' => 'Transaction not found'], 404);
        }
        
        DB::beginTransaction();
        try {
            $asset = $transaction->asset;
            
            // Reverse the balance change
            if ($type === 'income') {
                $asset->decrement('balance', $transaction->amount);
            } else {
                $asset->increment('balance', $transaction->amount);
            }
            
            $transaction->delete();
            
            DB::commit();
            
            return response()->json(['message' => 'Transaction deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to delete transaction'], 500);
        }
    }
}
