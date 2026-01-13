<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Subscription;

class CheckSubscription
{
    /**
     * Handle an incoming request.
     *
     * Check if user has active subscription for AI features
     */
    public function handle(Request $request, Closure $next, string $requiredPlan = 'basic')
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        // Get active subscription
        $subscription = Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->latest()
            ->first();

        // Check if subscription exists and is active
        if (!$subscription || !$subscription->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Active subscription required. Please subscribe to use this feature.',
                'required_plan' => $requiredPlan
            ], 403);
        }

        // Check plan level
        $planHierarchy = ['basic' => 1, 'pro' => 2];
        $userPlanLevel = $planHierarchy[$subscription->plan] ?? 0;
        $requiredPlanLevel = $planHierarchy[$requiredPlan] ?? 0;

        if ($userPlanLevel < $requiredPlanLevel) {
            return response()->json([
                'success' => false,
                'message' => "Fitur ini memerlukan paket {$requiredPlan} atau lebih tinggi.",
                'current_plan' => $subscription->plan,
                'required_plan' => $requiredPlan
            ], 403);
        }

        return $next($request);
    }
}
