<?php

namespace App\Http\Controllers;

use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    private WhatsAppService $whatsappService;

    public function __construct(WhatsAppService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    /**
     * Verify WhatsApp webhook (Meta requirement)
     *
     * @api GET /api/webhook/whatsapp
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function verifyWhatsApp(Request $request)
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === config('services.whatsapp.verify_token')) {
            Log::info('WhatsApp webhook verified successfully');
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::warning('WhatsApp webhook verification failed', [
            'mode' => $mode,
            'token' => $token
        ]);

        return response('Forbidden', 403);
    }

    /**
     * Receive WhatsApp messages from WAHA webhook
     *
     * @api POST /api/webhook/whatsapp
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function receiveWhatsApp(Request $request)
    {
        try {
            // WAHA webhook format
            $payload = $request->all();

            Log::info('WAHA Webhook received', ['payload' => $payload]);

            // Verify webhook signature (optional but recommended)
            if (config('services.whatsapp.webhook_secret')) {
                $signature = $request->header('X-Webhook-Signature');
                $expectedSignature = hash_hmac('sha256', $request->getContent(), config('services.whatsapp.webhook_secret'));

                if (!hash_equals($expectedSignature, $signature ?? '')) {
                    Log::warning('WAHA: Invalid webhook signature');
                    return response()->json(['error' => 'Invalid signature'], 401);
                }
            }

            // Handle message event
            if (isset($payload['event']) && $payload['event'] === 'message') {
                $this->whatsappService->handleIncomingMessage($payload);
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('WAHA: Error processing webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['success' => false], 500);
        }
    }
}
