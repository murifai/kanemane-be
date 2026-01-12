<?php

namespace App\Http\Controllers;

use App\Services\GeminiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class AIController extends Controller
{
    private GeminiService $geminiService;

    public function __construct(GeminiService $geminiService)
    {
        $this->geminiService = $geminiService;
    }

    /**
     * Parse natural language expense text
     *
     * @api POST /api/ai/parse-text
     * @param Request $request { "text": "makan siang 850" }
     * @return \Illuminate\Http\JsonResponse
     */
    public function parseText(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'text' => 'required|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->geminiService->parseText($request->text);

            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Scan receipt image using OCR
     *
     * @api POST /api/ai/scan-receipt
     * @param Request $request { "image": File }
     * @return \Illuminate\Http\JsonResponse
     */
    public function scanReceipt(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,jpg,png|max:10240' // Max 10MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Store uploaded image temporarily
            $image = $request->file('image');
            $path = $image->store('receipts', 'local');
            $fullPath = storage_path('app/' . $path);

            // Scan receipt
            $result = $this->geminiService->scanReceipt($fullPath);

            // Clean up temporary file
            Storage::disk('local')->delete($path);

            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            // Clean up on error
            if (isset($path)) {
                Storage::disk('local')->delete($path);
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Parse text and create expense in one step
     *
     * @api POST /api/ai/quick-expense
     * @param Request $request { "text": "makan 850", "asset_id": 1 }
     * @return \Illuminate\Http\JsonResponse
     */
    public function quickExpense(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'text' => 'required|string|max:500',
            'asset_id' => 'required|exists:assets,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Parse text with AI
            $parsed = $this->geminiService->parseText($request->text);

            // Get asset and verify ownership
            $asset = \App\Models\Asset::findOrFail($request->asset_id);

            if ($asset->owner_id !== auth()->id() && $asset->owner_type !== get_class(auth()->user())) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to asset'
                ], 403);
            }

            // Create expense
            $expense = \App\Models\Expense::create([
                'owner_type' => get_class(auth()->user()),
                'owner_id' => auth()->id(),
                'asset_id' => $asset->id,
                'category' => $parsed['category'],
                'amount' => $parsed['amount'],
                'currency' => 'JPY',
                'date' => now(),
                'note' => $parsed['description'],
                'created_by' => auth()->id(),
            ]);

            // Update asset balance
            $asset->decrement('balance', $parsed['amount']);

            return response()->json([
                'success' => true,
                'data' => [
                    'expense' => $expense,
                    'parsed' => $parsed,
                    'new_balance' => $asset->fresh()->balance
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
