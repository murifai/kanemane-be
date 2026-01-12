<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AssetController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Get personal assets
        $personalAssets = $user->personalAssets()->get()->map(function ($asset) use ($user) {
            $asset->is_primary_jpy = $asset->id === $user->primary_asset_jpy_id;
            $asset->is_primary_idr = $asset->id === $user->primary_asset_idr_id;
            return $asset;
        });
        
        // Get family assets
        $familyAssets = collect();
        foreach ($user->families as $family) {
            $familyAssets = $familyAssets->merge($family->assets->map(function ($asset) use ($user) {
                $asset->is_primary_jpy = $asset->id === $user->primary_asset_jpy_id;
                $asset->is_primary_idr = $asset->id === $user->primary_asset_idr_id;
                return $asset;
            }));
        }
        
        return response()->json([
            'personal' => $personalAssets,
            'family' => $familyAssets,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|in:tabungan,e-money,investasi,cash',
            'country' => 'required|in:JP,ID',
            'currency' => 'required|in:JPY,IDR',
            'balance' => 'required|numeric|min:0',
            'owner_type' => 'nullable|in:user,family',
            'family_id' => 'required_if:owner_type,family|exists:families,id',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $user = $request->user();
        $ownerType = $request->input('owner_type', 'user');
        
        if ($ownerType === 'family') {
            $ownerId = $request->input('family_id');
            $ownerClass = 'App\Models\Family';
        } else {
            $ownerId = $user->id;
            $ownerClass = 'App\Models\User';
        }
        
        $asset = Asset::create([
            'owner_type' => $ownerClass,
            'owner_id' => $ownerId,
            'name' => $request->name,
            'type' => $request->type,
            'country' => $request->country,
            'currency' => $request->currency,
            'balance' => $request->balance,
        ]);
        
        return response()->json($asset, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $asset = Asset::findOrFail($id);
        return response()->json($asset);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:tabungan,e-money,investasi,cash',
            'balance' => 'sometimes|numeric|min:0',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $asset = Asset::findOrFail($id);
        $asset->update($request->only(['name', 'type', 'balance']));
        
        return response()->json($asset);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $asset = Asset::findOrFail($id);
        $asset->delete();
        
        return response()->json(['message' => 'Asset deleted successfully']);
    }
}
