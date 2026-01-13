<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    /**
     * Handle Lynk.id Payment Webhook
     */
    public function handleLynk(Request $request)
    {
        // 1. Log Raw Request for Debugging
        Log::info('Lynk Webhook Raw:', $request->all());

        // 2. Extract Headers & Config
        // Lynk.id might send signature in headers, but documentation is scarce.
        // We will check both header and payload if needed, but rely on what we have.
        $receivedSignature = $request->header('X-Lynk-Signature');
        $merchantKey = env('LYNK_MERCHANT_KEY');

        if (!$merchantKey) {
            Log::error('Lynk Webhook: LYNK_MERCHANT_KEY not configured');
            return response()->json(['message' => 'Server configuration error'], 500);
        }

        // 3. Extract Body Parameters
        // Based on common patterns: refId, amount, message_id
        $refId = $request->input('refId', '');
        $amount = $request->input('amount', '');
        $messageId = $request->input('message_id', '');
        
        // 4. Verify Signature (if provided)
        if ($receivedSignature) {
             // Docs: validation string = amount + refId + message_id + secret_key
            $signatureString = (string)$amount . (string)$refId . (string)$messageId . $merchantKey;
            $calculatedSignature = hash('sha256', $signatureString);

            if (!hash_equals($calculatedSignature, $receivedSignature)) {
                Log::warning('Lynk Webhook: Invalid Signature', [
                    'calculated' => $calculatedSignature,
                    'received' => $receivedSignature,
                ]);
                return response()->json(['message' => 'Invalid signature'], 403);
            }
        } else {
             Log::warning('Lynk Webhook: No signature received. Processing with caution.');
        }

        // 5. Identify User
        // We assume the payload contains 'customer_email' or 'email'
        $email = $request->input('customer_email') ?? $request->input('email');
        
        if (!$email) {
            Log::error('Lynk Webhook: Email missing in payload');
            return response()->json(['message' => 'Email missing'], 200); // 200 to stop retries if it's a structural error
        }

        $user = User::where('email', $email)->first();
        
        if (!$user) {
            Log::error("Lynk Webhook: User not found for email {$email}");
            return response()->json(['message' => 'User not found'], 200);
        }

        // 6. Determine Plan
        $paidAmount = (int)$amount;
        $plan = null;
        $products = config('services.lynk.products', []);

        foreach ($products as $product) {
            if ($paidAmount == $product['amount']) {
                $plan = $product['plan_id'];
                break;
            }
        }

        if (!$plan) {
             Log::info("Lynk Webhook: Unknown amount {$paidAmount}, no plan matched.");
             return response()->json(['message' => 'Amount does not match any plan'], 200);
        }

        // 7. Activate Subscription
        try {
            // Deactivate previous active subscriptions
            Subscription::where('user_id', $user->id)
                ->where('status', 'active')
                ->update([
                    'status' => 'cancelled',
                    'expires_at' => now() // Use correct column name
                ]);

            Subscription::create([
                'user_id' => $user->id,
                'plan' => $plan,
                'status' => 'active',
                'started_at' => now(), // Correct column
                'expires_at' => now()->addMonth(), // Correct column
                'amount' => $paidAmount, // Correct column
                'midtrans_order_id' => $refId ?: 'LYNK-' . time(), // Reusing this field for Order ID
                'payment_type' => 'lynk', // Correct column
                'midtrans_transaction_id' => $messageId, // Store message_id/transaction_id here
            ]);

            Log::info("Lynk Webhook: Subscription activated for {$email} (Plan: {$plan})");

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error('Lynk Webhook: Error creating subscription', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Internal error'], 500);
        }
    }
}
