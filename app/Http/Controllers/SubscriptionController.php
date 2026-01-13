<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SubscriptionController extends Controller
{
    private MidtransService $midtransService;

    public function __construct(MidtransService $midtransService)
    {
        $this->midtransService = $midtransService;
    }

    /**
     * Get current subscription
     *
     * @api GET /api/subscription
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $user = auth()->user();

        $subscription = Subscription::where('user_id', $user->id)
            ->latest()
            ->first();

        if (!$subscription) {
            return response()->json([
                'success' => true,
                'data' => [
                    'has_subscription' => false,
                    'current_plan' => null
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'has_subscription' => true,
                'subscription' => $subscription,
                'is_active' => $subscription->isActive(),
                'is_expired' => $subscription->isExpired(),
            ]
        ]);
    }

    /**
     * Get available plans
     *
     * @api GET /api/subscription/plans
     * @return \Illuminate\Http\JsonResponse
     */
    public function plans()
    {
        $plans = [
            [
                'id' => 'basic',
                'name' => 'Basic',
                'price' => Subscription::getPlanPrice('basic'),
                'features' => Subscription::getPlanFeatures('basic'),
            ],
            [
                'id' => 'pro',
                'name' => 'Pro',
                'price' => Subscription::getPlanPrice('pro'),
                'features' => Subscription::getPlanFeatures('pro'),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $plans
        ]);
    }

    /**
     * Create payment for subscription
     *
     * @api POST /api/subscription/checkout
     * @param Request $request { "plan": "ai" }
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkout(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'plan' => 'required|in:basic,pro',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $plan = $request->plan;

            // Lynk.id URLs from env
            $paymentUrl = match($plan) {
                'basic' => env('LYNK_BASIC_URL'),
                'pro' => env('LYNK_PRO_URL'),
                default => null,
            };

            if (!$paymentUrl) {
                return response()->json([
                    'message' => 'Payment URL not configured for this plan'
                ], 500);
            }

            // We return it as redirect_url so frontend follows it
            return response()->json([
                'success' => true,
                'data' => [
                    'redirect_url' => $paymentUrl
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle Midtrans payment notification
     *
     * @api POST /api/webhook/midtrans
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleNotification(Request $request)
    {
        try {
            $this->midtransService->handleNotification($request->all());

            return response()->json([
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check payment status
     *
     * @api GET /api/subscription/status/{orderId}
     * @param string $orderId
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkStatus(string $orderId)
    {
        try {
            $status = $this->midtransService->checkTransactionStatus($orderId);

            return response()->json([
                'success' => true,
                'data' => $status
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel subscription
     *
     * @api POST /api/subscription/cancel
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancel()
    {
        try {
            $user = auth()->user();

            $subscription = Subscription::where('user_id', $user->id)
                ->where('status', 'active')
                ->latest()
                ->first();

            if (!$subscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active subscription found'
                ], 404);
            }

            $subscription->update([
                'status' => 'cancelled'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Subscription cancelled successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
