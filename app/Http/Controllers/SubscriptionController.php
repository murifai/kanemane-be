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
                    'current_plan' => 'free'
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
                'id' => 'manual',
                'name' => 'Manual',
                'price' => Subscription::getPlanPrice('manual'),
                'features' => Subscription::getPlanFeatures('manual'),
            ],
            [
                'id' => 'ai',
                'name' => 'AI',
                'price' => Subscription::getPlanPrice('ai'),
                'features' => Subscription::getPlanFeatures('ai'),
            ],
            [
                'id' => 'family_ai',
                'name' => 'Family AI',
                'price' => Subscription::getPlanPrice('family_ai'),
                'features' => Subscription::getPlanFeatures('family_ai'),
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
        $validator = Validator::make($request->all(), [
            'plan' => 'required|in:manual,ai,family_ai'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = auth()->user();

            // Check if user already has active subscription
            $activeSubscription = Subscription::where('user_id', $user->id)
                ->where('status', 'active')
                ->first();

            if ($activeSubscription && $activeSubscription->isActive()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have an active subscription'
                ], 400);
            }

            // Create payment
            $payment = $this->midtransService->createPayment($user, $request->plan);

            return response()->json([
                'success' => true,
                'data' => $payment
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
