<?php

namespace App\Services;

use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\Notification;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;

class MidtransService
{
    public function __construct()
    {
        Config::$serverKey = config('services.midtrans.server_key');
        Config::$isProduction = config('services.midtrans.is_production', false);
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    /**
     * Create payment for subscription
     *
     * @param \App\Models\User $user
     * @param string $plan
     * @return array
     */
    public function createPayment($user, string $plan): array
    {
        $amount = Subscription::getPlanPrice($plan);
        $orderId = 'SUB-' . $user->id . '-' . time();

        $params = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $amount,
            ],
            'customer_details' => [
                'first_name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? '',
            ],
            'item_details' => [[
                'id' => $plan,
                'price' => $amount,
                'quantity' => 1,
                'name' => "Kanemane {$plan} Plan (1 Month)",
            ]],
            'enabled_payments' => [
                'credit_card',
                'gopay',
                'bank_transfer',
                'echannel',
                'qris',
            ],
        ];

        try {
            $snapToken = Snap::getSnapToken($params);

            // Create pending subscription
            Subscription::create([
                'user_id' => $user->id,
                'plan' => $plan,
                'status' => 'pending',
                'midtrans_order_id' => $orderId,
                'amount' => $amount,
            ]);

            return [
                'snap_token' => $snapToken,
                'order_id' => $orderId,
            ];
        } catch (\Exception $e) {
            Log::error('Midtrans payment creation failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'plan' => $plan
            ]);

            throw new \Exception('Failed to create payment: ' . $e->getMessage());
        }
    }

    /**
     * Handle payment notification from Midtrans
     *
     * @param array $notificationData
     * @return void
     */
    public function handleNotification(array $notificationData): void
    {
        try {
            $notification = new Notification();

            $orderId = $notification->order_id;
            $transactionStatus = $notification->transaction_status;
            $fraudStatus = $notification->fraud_status ?? '';
            $transactionId = $notification->transaction_id;
            $paymentType = $notification->payment_type;

            Log::info('Midtrans notification received', [
                'order_id' => $orderId,
                'transaction_status' => $transactionStatus,
                'fraud_status' => $fraudStatus
            ]);

            // Find subscription
            $subscription = Subscription::where('midtrans_order_id', $orderId)->first();

            if (!$subscription) {
                Log::warning('Subscription not found for order', ['order_id' => $orderId]);
                return;
            }

            // Update subscription based on transaction status
            if ($transactionStatus == 'capture') {
                if ($fraudStatus == 'accept') {
                    $this->activateSubscription($subscription, $transactionId, $paymentType);
                }
            } elseif ($transactionStatus == 'settlement') {
                $this->activateSubscription($subscription, $transactionId, $paymentType);
            } elseif ($transactionStatus == 'pending') {
                $subscription->update([
                    'status' => 'pending',
                    'midtrans_transaction_id' => $transactionId,
                    'payment_type' => $paymentType,
                ]);
            } elseif (in_array($transactionStatus, ['deny', 'expire', 'cancel'])) {
                $subscription->update([
                    'status' => 'cancelled',
                    'midtrans_transaction_id' => $transactionId,
                    'payment_type' => $paymentType,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error handling Midtrans notification', [
                'error' => $e->getMessage(),
                'data' => $notificationData
            ]);
        }
    }

    /**
     * Activate subscription
     */
    private function activateSubscription(Subscription $subscription, string $transactionId, string $paymentType): void
    {
        $subscription->update([
            'status' => 'active',
            'started_at' => now(),
            'expires_at' => now()->addMonth(),
            'midtrans_transaction_id' => $transactionId,
            'payment_type' => $paymentType,
        ]);

        Log::info('Subscription activated', [
            'subscription_id' => $subscription->id,
            'user_id' => $subscription->user_id,
            'plan' => $subscription->plan
        ]);

        // TODO: Send activation email to user
    }

    /**
     * Check transaction status
     *
     * @param string $orderId
     * @return array
     */
    public function checkTransactionStatus(string $orderId): array
    {
        try {
            $status = \Midtrans\Transaction::status($orderId);

            return [
                'order_id' => $status->order_id,
                'status' => $status->transaction_status,
                'fraud_status' => $status->fraud_status ?? '',
                'payment_type' => $status->payment_type ?? '',
                'transaction_id' => $status->transaction_id ?? '',
            ];
        } catch (\Exception $e) {
            Log::error('Error checking transaction status', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);

            throw new \Exception('Failed to check transaction status');
        }
    }
}
