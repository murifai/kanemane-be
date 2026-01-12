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
            'primary_asset_id' => 'required|exists:assets,id',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Verify that the asset belongs to the user
        $asset = $user->personalAssets()->find($request->primary_asset_id);
        
        if (!$asset) {
            return response()->json(['error' => 'Asset not found or does not belong to user'], 403);
        }
        
        // Set primary asset
        $user->primary_asset_id = $request->primary_asset_id;
        $user->save();
        
        return response()->json([
            'message' => 'Onboarding completed successfully',
            'user' => $user->load('primaryAsset'),
        ]);
    }
}
