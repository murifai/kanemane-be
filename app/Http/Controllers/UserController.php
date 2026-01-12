<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Get current user profile
     */
    public function profile(Request $request)
    {
        $user = $request->user();
        return response()->json($user->load('primaryAsset'));
    }
    
    /**
     * Set primary asset for user
     */
    public function setPrimaryAsset(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'asset_id' => 'required|exists:assets,id',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $user = $request->user();
        
        // Verify that the asset belongs to the user
        $asset = $user->personalAssets()->find($request->asset_id);
        
        if (!$asset) {
            return response()->json(['error' => 'Asset not found or does not belong to user'], 403);
        }
        
        $user->primary_asset_id = $request->asset_id;
        $user->save();
        
        return response()->json([
            'message' => 'Primary asset updated successfully',
            'user' => $user->load('primaryAsset'),
        ]);
    }
}
