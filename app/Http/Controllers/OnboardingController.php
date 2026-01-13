<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class OnboardingController extends Controller
{
    /**
     * Check if user has completed onboarding
     */
    public function checkStatus(Request $request)
    {
        $user = $request->user();
        
        $hasAssets = $user->personalAssets()->exists();
        $hasPrimaryAsset = $user->primary_asset_id !== null;
        
        return response()->json([
            'completed' => $hasAssets && $hasPrimaryAsset,
            'has_assets' => $hasAssets,
            'has_primary_asset' => $hasPrimaryAsset,
        ]);
    }
    
    /**
     * Generate onboarding token for WhatsApp users
     */
    public function generateToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Find user by phone
        $phone = $request->phone;
        $user = User::where('phone', $phone)->first();
        
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        
        // Generate token
        $token = Str::random(64);
        
        // Store token in cache for 1 hour
        cache()->put("onboarding_token_{$token}", $user->id, now()->addHour());
        
        $onboardingUrl = config('app.frontend_url') . "/onboarding?token={$token}";
        
        return response()->json([
            'token' => $token,
            'url' => $onboardingUrl,
        ]);
    }
    
    /**
     * Verify onboarding token and get user
     */
    public function verifyToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $userId = cache()->get("onboarding_token_{$request->token}");
        
        if (!$userId) {
            return response()->json(['error' => 'Invalid or expired token'], 401);
        }
        
        $user = User::find($userId);
        
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        
        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
        ]);
    }
    
    /**
     * Complete onboarding by setting primary asset
     */
    public function complete(Request $request)
    {
        $user = $request->user();
        
        $validator = Validator::make($request->all(), [
            'primary_asset_jpy_id' => 'nullable|exists:assets,id',
            'primary_asset_idr_id' => 'nullable|exists:assets,id',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $userId = $user->id;

        // Validate JPY Asset ownership
        if ($request->filled('primary_asset_jpy_id')) {
            $assetJpy = $user->personalAssets()->where('id', $request->primary_asset_jpy_id)->first();
            if (!$assetJpy || $assetJpy->currency !== 'JPY') {
                return response()->json(['error' => 'Invalid JPY primary asset'], 422);
            }
            $user->primary_asset_jpy_id = $request->primary_asset_jpy_id;
        }

        // Validate IDR Asset ownership
        if ($request->filled('primary_asset_idr_id')) {
            $assetIdr = $user->personalAssets()->where('id', $request->primary_asset_idr_id)->first();
            if (!$assetIdr || $assetIdr->currency !== 'IDR') {
                return response()->json(['error' => 'Invalid IDR primary asset'], 422);
            }
            $user->primary_asset_idr_id = $request->primary_asset_idr_id;
        }

        // Ensure at least one primary asset is set if user has assets in that currency
        // This logic is slightly loose to avoid blocking, assuming frontend handles mandatory selection
        if (!$user->primary_asset_jpy_id && !$user->primary_asset_idr_id) {
             return response()->json(['error' => 'At least one primary asset must be selected'], 422);
        }
        
        // Use the old field as fallback or main indicator if needed, 
        // but for now we rely on the specific fields. 
        // We can just set the first available one as the "generic" primary if needed for legacy compatibility
        $user->primary_asset_id = $user->primary_asset_jpy_id ?? $user->primary_asset_idr_id;
        
        $user->save();
        
        return response()->json([
            'message' => 'Onboarding completed successfully',
            'user' => $user->load(['primaryAssetJpy', 'primaryAssetIdr']),
        ]);
    }
}
