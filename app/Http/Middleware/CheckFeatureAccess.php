<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckFeatureAccess
{
    /**
     * Handle an incoming request.
     *
     * Check if user has access to a specific feature based on their subscription
     * 
     * @param Request $request
     * @param Closure $next
     * @param string $feature The feature to check (export, scan, whatsapp)
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $feature)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        // Check if user can access the feature
        if (!$user->canAccessFeature($feature)) {
            $tier = $user->getSubscriptionTier();
            
            // Map feature to readable name
            $featureNames = [
                'export' => 'Export Laporan',
                'scan' => 'Scan Resi',
                'whatsapp' => 'Integrasi WhatsApp',
            ];
            
            $featureName = $featureNames[$feature] ?? $feature;
            
            return response()->json([
                'success' => false,
                'message' => "Fitur {$featureName} hanya tersedia untuk pengguna Pro. Silakan upgrade subscription Anda.",
                'current_tier' => $tier,
                'required_tier' => 'pro',
                'feature' => $feature,
            ], 403);
        }

        return $next($request);
    }
}
