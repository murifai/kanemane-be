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
        // 1. Extract Headers & Config
        $receivedSignature = $request->header('X-Lynk-Signature');
        $merchantKey = env('LYNK_MERCHANT_KEY');

        if (!$receivedSignature || !$merchantKey) {
            Log::warning('Lynk Webhook: Missing signature or merchant key configuration');
            return response()->json(['message' => 'Configuration error or missing signature'], 403);
        }

        // 2. Extract Body Parameters
        // Docs: validation string = amount + refId + message_id + secret_key
        $refId = $request->input('refId', '');
        $amount = $request->input('amount', '');
        $messageId = $request->input('message_id', '');
        
        // Ensure parameters are treated as strings for hashing
        $signatureString = (string)$amount . (string)$refId . (string)$messageId . $merchantKey;
        
        // 3. Calculate Signature (SHA256 Hex)
        $calculatedSignature = hash('sha256', $signatureString);

        // 4. Verify Signature
        // Using hash_equals for timing attack protection
        if (!hash_equals($calculatedSignature, $receivedSignature)) {
            Log::warning('Lynk Webhook: Invalid Signature', [
                'calculated' => $calculatedSignature,
                'received' => $receivedSignature,
                'payload' => $request->all()
            ]);
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        // 5. Process Payment
        // We assume the payload contains 'customer_email' or 'email' to identify the user
        $email = $request->input('customer_email') ?? $request->input('email');
        
        if (!$email) {
            Log::error('Lynk Webhook: Email missing in payload', $request->all());
            // We return 200 to acknowledge receipt even if we can't process, to prevent retries? 
            // Or 422? Usually 200 if it's a valid webhook but business logic failed.
            return response()->json(['message' => 'Email missing in payload'], 200);
        }

        $user = User::where('email', $email)->first();
        
        if (!$user) {
            Log::error("Lynk Webhook: User not found for email {$email}");
            return response()->json(['message' => 'User not found'], 200);
        }

        // 6. Determine Plan
        // Basic: 19000, Pro: 49000
        $paidAmount = (int)$amount;
        $plan = null;

        if ($paidAmount >= 19000 && $paidAmount < 49000) {
            $plan = 'basic';
        } elseif ($paidAmount >= 49000) {
            $plan = 'pro';
        } else {
             Log::info("Lynk Webhook: Unknown amount {$paidAmount}, no plan matched.");
             return response()->json(['message' => 'Amount does not match any plan'], 200);
        }

        // 7. Activate Subscription
        // Deactivate previous active subscriptions
        Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->update(['status' => 'cancelled', 'ends_at' => now()]);

        Subscription::create([
            'user_id' => $user->id,
            'plan' => $plan,
            'status' => 'active',
            'starts_at' => now(),
            // Assume monthly for now
            'ends_at' => now()->addMonth(),
            'price' => $paidAmount,
            'order_id' => $refId ?: 'LYNK-' . time(),
            'payment_method' => 'lynk',
            'payment_status' => 'paid'
        ]);

        Log::info("Lynk Webhook: Subscription activated for {$email} (Plan: {$plan})");

        return response()->json(['success' => true]);
    }
}
