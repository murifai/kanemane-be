<?php

namespace App\Services;

use App\Models\User;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Asset;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WhatsAppService
{
    private string $baseUrl;
    private string $session;
    private ?string $apiKey;
    private GeminiService $geminiService;

    public function __construct(GeminiService $geminiService)
    {
        $this->baseUrl = config('services.whatsapp.waha_url', 'http://localhost:3000');
        $this->session = config('services.whatsapp.waha_session', 'kanemane-production');
        $this->apiKey = config('services.whatsapp.waha_api_key');
        $this->geminiService = $geminiService;
    }

    /**
     * Get HTTP client with API key header if configured
     */
    private function getHttpClient()
    {
        $client = Http::timeout(30);

        if ($this->apiKey) {
            $client = $client->withHeaders([
                'X-Api-Key' => $this->apiKey
            ]);
        }

        return $client;
    }

    /**
     * Format phone number to WhatsApp chat ID
     * Handles both regular phone numbers and existing chat IDs (@c.us or @lid)
     * Example: +6281234567890 -> 6281234567890@c.us
     *          279383612866604@lid -> 279383612866604@lid (preserved)
     */
    private function formatChatId(string $phone): string
    {
        // If already a WhatsApp chat ID (contains @c.us or @lid), return as-is
        if (str_contains($phone, '@c.us') || str_contains($phone, '@lid')) {
            return $phone;
        }

        // Remove all non-numeric characters
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        // Add @c.us suffix for individual chats
        return $cleaned . '@c.us';
    }

    /**
     * Send text message to user
     */
    public function sendMessage(string $to, string $message): void
    {
        try {
            $response = $this->getHttpClient()->post("{$this->baseUrl}/api/sessions/{$this->session}/text", [
                'chatId' => $this->formatChatId($to),
                'text' => $message,
                'session' => $this->session
            ]);

            if (!$response->successful()) {
                Log::error('WAHA: Failed to send message', [
                    'to' => $to,
                    'response' => $response->json()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('WAHA: Error sending message', [
                'error' => $e->getMessage(),
                'to' => $to
            ]);
        }
    }

    /**
     * Send message with interactive buttons
     */
    public function sendButtons(string $to, string $text, array $buttons): void
    {
        try {
            // WAHA uses different button format
            $wahaButtons = array_map(function($button, $index) {
                return [
                    'id' => $button['reply']['id'] ?? "btn_{$index}",
                    'text' => $button['reply']['title'] ?? "Button {$index}"
                ];
            }, $buttons, array_keys($buttons));

            $response = $this->getHttpClient()->post("{$this->baseUrl}/api/sessions/{$this->session}/buttons", [
                'chatId' => $this->formatChatId($to),
                'text' => $text,
                'buttons' => $wahaButtons,
                'session' => $this->session
            ]);

        if (!$response->successful()) {
            // Fallback for engines that don't support buttons (like WEBJS)
            if ($response->status() === 501) {
                $menuText = $text . "\n\n";
                foreach ($buttons as $index => $button) {
                    $menuText .= ($index + 1) . ". " . ($button['reply']['title'] ?? "Option {$index}") . "\n";
                }
                $menuText .= "\nKetik angka pilihan (contoh: 1)";
                $this->sendMessage($to, $menuText);
                return;
            }

            Log::error('WAHA: Failed to send buttons', [
                'to' => $to,
                'response' => $response->json()
            ]);
        }
        } catch (\Exception $e) {
            Log::error('WAHA: Error sending buttons', [
                'error' => $e->getMessage(),
                'to' => $to
            ]);
        }
    }

    /**
     * Send document/file to user
     */
    public function sendDocument(string $to, string $filePath, string $filename): void
    {
        try {
            // Get file from storage
            $fileContent = Storage::get($filePath);
            $base64 = base64_encode($fileContent);
            $mimeType = Storage::mimeType($filePath);
            
            $response = $this->getHttpClient()->post("{$this->baseUrl}/api/sessions/{$this->session}/file", [
                'chatId' => $this->formatChatId($to),
                'file' => [
                    'mimetype' => $mimeType,
                    'filename' => $filename,
                    'data' => $base64,
                ],
                'session' => $this->session
            ]);

            if (!$response->successful()) {
                Log::error('WAHA: Failed to send document', [
                    'to' => $to,
                    'filename' => $filename,
                    'response' => $response->json()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('WAHA: Error sending document', [
                'error' => $e->getMessage(),
                'to' => $to,
                'filename' => $filename
            ]);
        }
    }

    /**
     * Normalize phone number to international format without country code prefix
     * Removes all non-numeric characters and handles Indonesian numbers
     * Examples:
     *   +6281234567890 -> 6281234567890
     *   081234567890 -> 6281234567890
     *   6281234567890 -> 6281234567890
     */
    private function normalizePhone(string $phone): string
    {
        // Remove all non-numeric characters (including invisible Unicode chars)
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        
        // Handle Indonesian local format (08xxx -> 628xxx)
        if (str_starts_with($cleaned, '08')) {
            $cleaned = '62' . substr($cleaned, 1);
        }
        
        // Remove leading + if present
        $cleaned = ltrim($cleaned, '+');
        
        return $cleaned;
    }

    /**
     * Convert LID (Linked ID) to phone number using WAHA API
     * LIDs are privacy-protected identifiers that mask phone numbers
     * Returns the actual phone number or null if not found
     */
    private function convertLidToPhone(string $lid): ?string
    {
        try {
            $response = $this->getHttpClient()->get("{$this->baseUrl}/api/sessions/{$this->session}/contacts/{$lid}");
            
            if ($response->successful()) {
                $data = $response->json();
                
                // WAHA returns 'pn' field with format: "6285175109501@c.us"
                $pn = $data['pn'] ?? null;
                
                if ($pn) {
                    // Remove @c.us suffix to get just the phone number
                    return str_replace('@c.us', '', $pn);
                }
                
                return null;
            }
            
            Log::warning('WAHA: Failed to convert LID to phone', [
                'lid' => $lid,
                'response' => $response->json()
            ]);
            
            return null;
        } catch (\Exception $e) {
            Log::error('WAHA: Error converting LID', [
                'lid' => $lid,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

    /**
     * Generate onboarding URL for new user
     */
    private function generateOnboardingUrl(string $phone): string
    {
        try {
            $response = Http::post(config('app.url') . '/api/onboarding/generate-token', [
                'phone' => $phone
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                return $data['url'] ?? config('app.frontend_url') . '/onboarding';
            }
            
            return config('app.frontend_url') . '/onboarding';
        } catch (\Exception $e) {
            Log::error('Failed to generate onboarding URL', [
                'error' => $e->getMessage(),
                'phone' => $phone
            ]);
            
            return config('app.frontend_url') . '/onboarding';
        }
    }

    /**
     * Normalize category from Japanese to Indonesian
     */
    private function normalizeCategory(string $category): string
    {
        $map = [
            '食費' => 'Makanan',
            '交通費' => 'Transportasi',
            '家賃' => 'Sewa',
            '光熱費' => 'Utilitas',
            'その他' => 'Lainnya',
        ];

        return $map[$category] ?? $category;
    }

    /**
     * Get random greeting message
     */
    private function getRandomGreeting(string $name): string
    {
        $greetings = [
            "Halo {$name}! Jajan apa hari ini? 🍜",
            "Konnichiwa {$name}! Belanja apa hari ini? 🛍️",
            "Hai {$name}! Ada pengeluaran baru? 💸",
            "Halo {$name}! Mau catat transaksi? 📝",
            "Konnichiwa! Gimana kabar keuangan hari ini, {$name}? 💰",
        ];
        
        return $greetings[array_rand($greetings)];
    }

    /**
     * Handle incoming WhatsApp message from WAHA webhook
     */
    public function handleIncomingMessage(array $payload): void
    {
        // WAHA webhook format
        $event = $payload['event'] ?? null;

        if ($event !== 'message') {
            return;
        }

        $message = $payload['payload'] ?? [];
        $from = $message['from'] ?? null;

        if (!$from) {
            Log::warning('WAHA: Message without sender');
            return;
        }

        // Extract phone number from chat ID (remove @c.us or @lid)
        $phone = str_replace(['@c.us', '@lid'], '', $from);
        
        // If this is a LID, try to convert it to phone number
        if (str_contains($from, '@lid')) {
            Log::info('WAHA: Detected LID format, attempting conversion', [
                'from' => $from,
                'lid' => $phone
            ]);
            
            $convertedPhone = $this->convertLidToPhone($phone);
            
            if ($convertedPhone) {
                Log::info('WAHA: Successfully converted LID to phone', [
                    'lid' => $phone,
                    'phone' => $convertedPhone
                ]);
                $phone = $convertedPhone;
            } else {
                Log::warning('WAHA: Could not convert LID to phone number', [
                    'lid' => $phone
                ]);
            }
        }
        
        // Normalize the phone number
        $normalizedPhone = $this->normalizePhone($phone);
        
        Log::info('WAHA: Looking for user', [
            'from' => $from,
            'extracted_phone' => $phone,
            'normalized_phone' => $normalizedPhone
        ]);

        // Find user by normalized phone number
        $user = User::whereNotNull('phone')
            ->get()
            ->first(function($u) use ($normalizedPhone) {
                $userNormalized = $this->normalizePhone($u->phone);
                return $userNormalized === $normalizedPhone;
            });

        if (!$user) {
            // User not registered - send onboarding link
            $onboardingUrl = $this->generateOnboardingUrl($normalizedPhone);
            $this->sendMessage($from, "Halo! 👋\n\nNomor kamu sepertinya belum terdaftar nih.\n\nDaftarin di sini ya:\n{$onboardingUrl}");
            return;
        }

        // Check if user has completed onboarding
        $hasAssets = $user->personalAssets()->exists();
        $hasPrimaryAsset = $user->primary_asset_jpy_id !== null || $user->primary_asset_idr_id !== null;

        if (!$hasAssets || !$hasPrimaryAsset) {
            $onboardingUrl = config('app.frontend_url') . '/onboarding';
            $this->sendMessage($from, "Halo {$user->name}! 👋\n\nKamu belum menyelesaikan setup awal nih.\n\nYuk lengkapi di sini:\n{$onboardingUrl}");
            return;
        }

        // Check if user has Pro subscription for WhatsApp integration
        if (!$user->canAccessFeature('whatsapp')) {
            $tier = $user->getSubscriptionTier();
            $upgradeUrl = config('app.frontend_url') . '/subscription';
            
            $message = "🔒 *Fitur Pro Diperlukan*\n\n";
            $message .= "Integrasi WhatsApp hanya tersedia untuk pengguna Pro.\n\n";
            $message .= "Anda saat ini menggunakan paket {$tier}.\n\n";
            $message .= "Upgrade ke Pro untuk:\n";
            $message .= "✅ Integrasi WhatsApp\n";
            $message .= "✅ Scan Resi\n";
            $message .= "✅ Export Laporan\n";
            $message .= "✅ AI Parsing\n\n";
            $message .= "Upgrade di: {$upgradeUrl}";
            
            $this->sendMessage($from, $message);
            return;
        }

        try {
            // Handle different message types
            if (isset($message['hasMedia']) && $message['hasMedia']) {
                $this->handleMediaMessage($message, $user, $from);
                return;
            }

            // Handle button reply
            if (isset($message['selectedButtonId'])) {
                $this->handleButtonReply($message['selectedButtonId'], $user, $from);
                return;
            }

            // Handle text message
            if (isset($message['body'])) {
                $this->handleTextMessage($message['body'], $user, $from);
                return;
            }

            // Unknown message type
            $this->sendMessage($from, "❓ Maaf, tipe pesan tidak didukung.\n\nKirim teks seperti: makan 850\natau foto struk belanja.");
        } catch (\Exception $e) {
            Log::error('WAHA: Error handling message', [
                'error' => $e->getMessage(),
                'from' => $from,
                'message' => $message
            ]);

            $this->sendMessage($from, "❌ Terjadi kesalahan: {$e->getMessage()}");
        }
    }

    /**
     * Handle text message
     */
    private function handleTextMessage(string $text, User $user, string $from): void
    {
        // Check for special commands
        $text = trim($text);

        // Handle pending actions (replies to text menus)
        if ($this->handlePendingAction($text, $user, $from)) {
            return;
        }

        $lowerText = strtolower($text);

        // Check for saldo command (with optional asset name or currency)
        if ($lowerText === 'saldo' || $lowerText === 'balance') {
            $this->handleBalanceCommand($user, $from);
            return;
        }
        
        // Check for specific asset/currency saldo (e.g., "saldo paypay", "saldo JPY")
        if (preg_match('/^(saldo|balance)\s+(.+)$/i', $text, $matches)) {
            $query = trim($matches[2]);
            $this->handleSpecificBalanceCommand($user, $from, $query);
            return;
        }

        // Check for wallet management command
        if ($lowerText === '/dompet' || $lowerText === '/wallet') {
            $this->handleWalletCommand($user, $from);
            return;
        }

         // Check for transaction history command
        if ($lowerText === 'riwayat' || $lowerText === 'history') {
            $this->handleHistoryCommand($user, $from);
            return;
        }
        
        // Check for filtered history
        if (preg_match('/^(riwayat|history)\s+(.+)$/i', $text, $matches)) {
            $filter = trim($matches[2]);
            $this->handleHistoryCommand($user, $from, $filter);
            return;
        }

        // Check for summary command
        if ($lowerText === '/ringkasan' || $lowerText === '/summary' || $lowerText === 'ringkasan') {
            $this->handleSummaryCommand($user, $from);
            return;
        }

        // Check for export command
        if ($lowerText === '/export' || $lowerText === 'export') {
            $this->handleExportCommand($user, $from);
            return;
        }

        // Check for asset creation command
        if ($lowerText === '/tambah aset' || $lowerText === '/add asset') {
            $this->handleAssetCreationStart($user, $from);
            return;
        }

        // Check for asset edit command
        if (preg_match('/^\/(edit|ubah)\s+aset\s+(.+)$/i', $text, $matches)) {
            $assetName = trim($matches[2]);
            $this->handleAssetEditStart($user, $from, $assetName);
            return;
        }

        // Check for asset delete command
        if (preg_match('/^\/(hapus|delete)\s+aset\s+(.+)$/i', $text, $matches)) {
            $assetName = trim($matches[2]);
            $this->handleAssetDeleteStart($user, $from, $assetName);
            return;
        }

        if ($lowerText === 'help' || $lowerText === 'bantuan') {
            $this->sendHelpMessage($from);
            return;
        }

        // If not a command, check if it's a transaction or just random chat
        $isLikelyTransaction = preg_match('/\d+/', $text); // Contains numbers
        
        if (!$isLikelyTransaction) {
            // Random message - send greeting
            $greeting = $this->getRandomGreeting($user->name);
            $this->sendMessage($from, $greeting);
            return;
        }

        // Parse transaction with AI
        $this->handleTransactionMessage($text, $user, $from);
    }

    /**
     * Handle balance command - show all assets grouped by currency
     */
    private function handleBalanceCommand(User $user, string $from): void
    {
        $assets = $user->personalAssets()->get();
        
        if ($assets->isEmpty()) {
            $this->sendMessage($from, "❌ Anda belum memiliki asset.\n\nSilakan buat asset di kanemane.com terlebih dahulu.");
            return;
        }
        
        $message = "💰 *Total Saldo Anda*\n\n";
        $totalByCurrency = [];
        
        foreach ($assets as $a) {
            $currencySymbol = match($a->currency) {
                'JPY' => '¥',
                'IDR' => 'Rp',
                'USD' => '$',
                default => $a->currency . ' '
            };
            
            $isPrimaryJpy = $a->id === $user->primary_asset_jpy_id;
            $isPrimaryIdr = $a->id === $user->primary_asset_idr_id;
            $isPrimary = ($isPrimaryJpy || $isPrimaryIdr) ? ' 🌟' : '';
            $message .= "• {$a->name}{$isPrimary}: {$currencySymbol}" . number_format($a->balance, 0, ',', '.') . "\n";
            
            // Sum by currency
            if (!isset($totalByCurrency[$a->currency])) {
                $totalByCurrency[$a->currency] = 0;
            }
            $totalByCurrency[$a->currency] += $a->balance;
        }
        
        // Show totals if multiple currencies
        if (count($totalByCurrency) > 1) {
            $message .= "\n*Total per Mata Uang:*\n";
            foreach ($totalByCurrency as $currency => $total) {
                $currencySymbol = match($currency) {
                    'JPY' => '¥',
                    'IDR' => 'Rp',
                    'USD' => '$',
                    default => $currency . ' '
                };
                $message .= "• {$currencySymbol}" . number_format($total, 0, ',', '.') . "\n";
            }
        }
        
        $this->sendMessage($from, $message);
    }

    /**
     * Handle specific balance command - show balance for specific asset or currency
     */
    private function handleSpecificBalanceCommand(User $user, string $from, string $query): void
    {
        $query = strtolower($query);
        
        // Check if query is a currency (JPY, IDR, USD)
        if (in_array(strtoupper($query), ['JPY', 'IDR', 'USD'])) {
            $currency = strtoupper($query);
            $assets = $user->personalAssets()->where('currency', $currency)->get();
            
            if ($assets->isEmpty()) {
                $this->sendMessage($from, "❌ Anda tidak memiliki asset dengan mata uang {$currency}.");
                return;
            }
            
            $currencySymbol = match($currency) {
                'JPY' => '¥',
                'IDR' => 'Rp',
                'USD' => '$',
                default => $currency . ' '
            };
            
            $message = "💰 *Saldo {$currency}*\n\n";
            $total = 0;
            
            foreach ($assets as $a) {
                $isPrimaryJpy = $a->id === $user->primary_asset_jpy_id;
                $isPrimaryIdr = $a->id === $user->primary_asset_idr_id;
                $isPrimary = ($isPrimaryJpy || $isPrimaryIdr) ? ' 🌟' : '';
                $message .= "• {$a->name}{$isPrimary}: {$currencySymbol}" . number_format($a->balance, 0, ',', '.') . "\n";
                $total += $a->balance;
            }
            
            $message .= "\n*Total: {$currencySymbol}" . number_format($total, 0, ',', '.') . "*";
            
            $this->sendMessage($from, $message);
            return;
        }
        
        // Otherwise, find asset by name (case-insensitive)
        $specificAsset = $user->personalAssets()
            ->whereRaw('LOWER(name) = ?', [$query])
            ->first();
        
        if (!$specificAsset) {
            $this->sendMessage($from, "❌ Asset '{$query}' tidak ditemukan.\n\nKirim 'saldo' untuk melihat semua asset Anda.");
            return;
        }
        
        $currencySymbol = match($specificAsset->currency) {
            'JPY' => '¥',
            'IDR' => 'Rp',
            'USD' => '$',
            default => $specificAsset->currency . ' '
        };
        
        $message = "💰 *Saldo {$specificAsset->name}*\n\n" .
                  "{$currencySymbol}" . number_format($specificAsset->balance, 0, ',', '.');
        
        $this->sendMessage($from, $message);
    }

    /**
     * Handle wallet management command
     */
    private function handleWalletCommand(User $user, string $from): void
    {
        $assets = $user->personalAssets()->get();
        
        if ($assets->isEmpty()) {
            $this->sendMessage($from, "❌ Anda belum memiliki asset.\n\nSilakan buat asset di kanemane.com terlebih dahulu.");
            return;
        }
        
        $message = "🏦 *Dompet Utama*\n\n";
        
        // Show JPY primary wallet
        if ($user->primary_asset_jpy_id) {
            $primaryJpy = $user->primaryAssetJpy;
            $message .= "JPY: *{$primaryJpy->name}* 🌟\n";
        } else {
            $message .= "JPY: Belum diset\n";
        }
        
        // Show IDR primary wallet
        if ($user->primary_asset_idr_id) {
            $primaryIdr = $user->primaryAssetIdr;
            $message .= "IDR: *{$primaryIdr->name}* 🌟\n";
        } else {
            $message .= "IDR: Belum diset\n";
        }
        
        $message .= "\nUntuk mengganti dompet utama, silakan buka:\n";
        $message .= config('app.frontend_url') . '/dashboard/assets';
        
        $this->sendMessage($from, $message);
    }

    /**
     * Handle transaction message with AI parsing
     */
    private function handleTransactionMessage(string $text, User $user, string $from): void
    {
        // Get user's assets for AI matching
        $userAssets = $user->personalAssets()->get()->map(function($asset) {
            return [
                'id' => $asset->id,
                'name' => $asset->name,
                'currency' => $asset->currency,
            ];
        })->toArray();

        // Parse transaction with AI
        $parsed = $this->geminiService->parseTransaction($text, $userAssets);

        // Normalize category
        $parsed['category'] = $this->normalizeCategory($parsed['category']);

        // Determine which asset to use
        $asset = null;
        
        if ($parsed['asset_name']) {
            // Find asset by name (case-insensitive)
            $asset = $user->personalAssets()
                ->whereRaw('LOWER(name) = ?', [strtolower($parsed['asset_name'])])
                ->first();
        }
        
        // If asset not found by name, try to find by currency if explicitly mentioned
        if (!$asset && $parsed['currency']) {
             $asset = $user->personalAssets()
                ->where('currency', $parsed['currency'])
                ->first();
        }

        if (!$asset) {
            // Use primary asset based on parsed currency
            if ($parsed['currency'] === 'JPY' && $user->primary_asset_jpy_id) {
                $asset = $user->primaryAssetJpy;
            } elseif ($parsed['currency'] === 'IDR' && $user->primary_asset_idr_id) {
                $asset = $user->primaryAssetIdr;
            } else {
                // Fallback: try to find any asset with matching currency
                $asset = $user->personalAssets()
                    ->where('currency', $parsed['currency'])
                    ->first();
            }
        }
        
        if (!$asset) {
            $this->sendMessage($from, "❌ Tidak dapat menentukan asset untuk transaksi ini.\n\nSilakan set dompet utama dengan perintah /dompet");
            return;
        }

        // Always ask for confirmation as requested
        $this->confirmTransaction($text, $user, $from, $parsed, $asset);
    }

    /**
     * Ask for confirmation before creating transaction
     */
    private function confirmTransaction(string $originalText, User $user, string $from, array $parsed, Asset $asset): void
    {
        $currencySymbol = match($parsed['currency']) {
            'JPY' => '¥',
            'IDR' => 'Rp',
            'USD' => '$',
            default => $parsed['currency'] . ' '
        };

        $typeLabel = $parsed['type'] === 'income' ? 'Pemasukan' : 'Pengeluaran';
        
        $message = "📝 *Konfirmasi Transaksi*\n\n";
        $message .= "Tipe: {$typeLabel}\n";
        $message .= "Item: {$parsed['description']}\n";
        $message .= "Jumlah: {$currencySymbol}" . number_format($parsed['amount'], 0, ',', '.') . "\n";
        $message .= "Kategori: {$parsed['category']}\n";
        $message .= "Asset: {$asset->name} ({$asset->currency})\n\n";
        
        if ($parsed['currency'] !== $asset->currency) {
            $message .= "⚠️ *Perhatian*: Mata uang transaksi ({$parsed['currency']}) beda dengan asset ({$asset->currency}).\n\n";
        }
        
        $message .= "Apakah data ini benar?";
        
        // Store pending transaction for confirmation
        cache()->put("pending_transaction_{$from}", [
            'user_id' => $user->id,
            'asset_id' => $asset->id,
            'parsed' => $parsed,
            'original_text' => $originalText,
        ], now()->addMinutes(5));
        
        $this->sendButtons($from, $message, [
            [
                'reply' => [
                    'id' => 'confirm_transaction',
                    'title' => '✅ Ya, Benar'
                ]
            ],
            [
                'reply' => [
                    'id' => 'cancel_transaction',
                    'title' => '❌ Batal'
                ]
            ]
        ]);
    }



    /**
     * Create transaction (income or expense)
     */
    private function createTransaction(User $user, Asset $asset, array $parsed, string $from): void
    {
        if ($parsed['type'] === 'income') {
            // Create income
            $income = Income::create([
                'owner_type' => User::class,
                'owner_id' => $user->id,
                'asset_id' => $asset->id,
                'category' => $parsed['category'],
                'amount' => $parsed['amount'],
                'currency' => $asset->currency,
                'date' => now(),
                'note' => $parsed['description'],
                'created_by' => $user->id,
            ]);

            // Update balance
            $asset->increment('balance', $income->amount);
            $newBalance = $asset->fresh()->balance;

            $currencySymbol = match($asset->currency) {
                'JPY' => '¥',
                'IDR' => 'Rp',
                'USD' => '$',
                default => $asset->currency . ' '
            };

            // Send confirmation
            $message = "✅ *Pemasukan tercatat!*\n\n" .
                       "Asset: {$asset->name}\n" .
                       "Kategori: {$income->category}\n" .
                       "Jumlah: {$currencySymbol}" . number_format($income->amount, 0, ',', '.') . "\n" .
                       "Note: {$income->note}\n\n" .
                       "💰 Saldo: {$currencySymbol}" . number_format($newBalance, 0, ',', '.');

            $this->sendMessage($from, $message);
        } else {
            // Create expense
            $expense = Expense::create([
                'owner_type' => User::class,
                'owner_id' => $user->id,
                'asset_id' => $asset->id,
                'category' => $parsed['category'],
                'amount' => $parsed['amount'],
                'currency' => $asset->currency,
                'date' => now(),
                'note' => $parsed['description'],
                'created_by' => $user->id,
            ]);

            // Update balance
            $asset->decrement('balance', $expense->amount);
            $newBalance = $asset->fresh()->balance;

            $currencySymbol = match($asset->currency) {
                'JPY' => '¥',
                'IDR' => 'Rp',
                'USD' => '$',
                default => $asset->currency . ' '
            };

            // Send confirmation
            $message = "✅ *Pengeluaran tercatat!*\n\n" .
                       "Asset: {$asset->name}\n" .
                       "Kategori: {$expense->category}\n" .
                       "Jumlah: {$currencySymbol}" . number_format($expense->amount, 0, ',', '.') . "\n" .
                       "Note: {$expense->note}\n\n" .
                       "💰 Saldo: {$currencySymbol}" . number_format($newBalance, 0, ',', '.');

            $this->sendMessage($from, $message);
        }
    }

    /**
     * Send help message
     */
    private function sendHelpMessage(string $from): void
    {
        $helpText = "📱 *Panduan Kanemane WhatsApp Bot*\n\n" .
                    "Cara mencatat transaksi:\n" .
                    "• Kirim teks: \"makan 850\" atau \"gajian 5000000\"\n" .
                    "• Sebutkan asset: \"jajan 500 pake PayPay\"\n" .
                    "• Atau foto struk belanja\n\n" .
                    "Perintah:\n" .
                    "• saldo - Cek semua saldo\n" .
                    "• saldo [nama asset] - Cek saldo spesifik\n" .
                    "• saldo JPY - Cek total saldo JPY\n" .
                    "• /dompet - Kelola dompet utama\n" .
                    "• help - Panduan ini";
        $this->sendMessage($from, $helpText);
    }

    /**
     * Handle media message (images/receipts)
     */
    private function handleMediaMessage(array $message, User $user, string $from): void
    {
        try {
            // Log the full message payload for debugging
            Log::info('WAHA: Media message payload', [
                'message' => $message
            ]);
            
            // Get media URL from webhook payload
            // WAHA can send media in different formats:
            // - Nested: media.url
            // - Flat: mediaUrl
            $mediaUrl = $message['media']['url'] ?? $message['mediaUrl'] ?? null;
            
            if (!$mediaUrl) {
                Log::error('WAHA: No mediaUrl in message payload', [
                    'message_id' => $message['id']
                ]);
                $this->sendMessage($from, "❌ Gagal mengunduh gambar. Silakan coba lagi.");
                return;
            }
            
            // Download media from WAHA
            $tempPath = $this->downloadMedia($mediaUrl);

            if (!$tempPath) {
                $this->sendMessage($from, "❌ Gagal mengunduh gambar. Silakan coba lagi.");
                return;
            }

            // Scan receipt with AI
            $scanResult = $this->geminiService->scanReceipt($tempPath);

            // Normalize category
            $scanResult['category'] = $this->normalizeCategory($scanResult['category']);

            // Store scan result temporarily for confirmation
            cache()->put("whatsapp_receipt_{$from}", $scanResult, now()->addMinutes(5));

            $detectedCurrency = $scanResult['currency'] ?? 'JPY';
            $currencySymbol = match($detectedCurrency) {
                'IDR' => 'Rp',
                'USD' => '$',
                default => '¥'
            };

            // Send confirmation with buttons
            $message = "📄 *Struk terdeteksi!*\n\n" .
                       "Merchant: {$scanResult['merchant']}\n" .
                       "Tanggal: {$scanResult['date']}\n" .
                       "Total: {$currencySymbol}" . number_format($scanResult['amount'], 0, ',', '.') . "\n" .
                       "Kategori: {$scanResult['category']}\n\n" .
                       "Simpan pengeluaran ini?";

            $this->sendButtons($from, $message, [
                [
                    'reply' => [
                        'id' => 'confirm_receipt',
                        'title' => '✅ Ya, simpan'
                    ]
                ],
                [
                    'reply' => [
                        'id' => 'cancel_receipt',
                        'title' => '❌ Batal'
                    ]
                ]
            ]);

            // Clean up downloaded file
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        } catch (\Exception $e) {
            Log::error('WAHA: Error handling media', [
                'error' => $e->getMessage(),
                'message_id' => $message['id']
            ]);

            $this->sendMessage($from, "❌ Gagal memproses gambar: {$e->getMessage()}");
        }
    }

    /**
     * Handle button reply
     */
    private function handleButtonReply(string $buttonId, User $user, string $from): void
    {
        Log::info('WAHA: Handling button reply', [
            'button_id' => $buttonId,
            'from' => $from,
            'user_id' => $user->id
        ]);

        if ($buttonId === 'confirm_receipt') {
            $scanResult = cache()->get("whatsapp_receipt_{$from}");
            
            Log::info('WAHA: Checking cache for receipt', [
                'key' => "whatsapp_receipt_{$from}",
                'found' => $scanResult ? 'yes' : 'no',
                'scan_result' => $scanResult
            ]);

            if (!$scanResult) {
                $this->sendMessage($from, "❌ Data struk sudah kadaluarsa. Silakan kirim ulang foto struk.");
                return;
            }

            // Attempt to find asset matching receipt currency
            $detectedCurrency = $scanResult['currency'] ?? 'JPY';
            $asset = $user->personalAssets()->where('currency', $detectedCurrency)->first();

            if (!$asset) {
                // Fallback to primary asset
                $asset = $user->primaryAsset;
            }

            if (!$asset) {
                $this->sendMessage($from, "❌ Anda belum set dompet utama. Gunakan perintah /dompet");
                return;
            }

            try {
                // Create expense
                $expense = Expense::create([
                    'owner_type' => User::class,
                    'owner_id' => $user->id,
                    'asset_id' => $asset->id,
                    'category' => $scanResult['category'],
                    'amount' => $scanResult['amount'],
                    'currency' => $asset->currency,
                    'date' => $scanResult['date'],
                    'note' => "Receipt from {$scanResult['merchant']}",
                    'created_by' => $user->id,
                ]);

                Log::info('WAHA: Expense created', ['expense_id' => $expense->id]);

                // Update balance
                $asset->decrement('balance', $expense->amount);
                $newBalance = $asset->fresh()->balance;

                $currencySymbol = match($asset->currency) {
                    'JPY' => '¥',
                    'IDR' => 'Rp',
                    'USD' => '$',
                    default => $asset->currency . ' '
                };

                // Clear cache
                cache()->forget("whatsapp_receipt_{$from}");

                // Send confirmation
                $message = "✅ *Pengeluaran tersimpan!*\n\n" .
                           "Kategori: {$expense->category}\n" .
                           "Jumlah: {$currencySymbol}" . number_format($expense->amount, 0, ',', '.') . "\n" .
                           "Merchant: {$scanResult['merchant']}\n\n" .
                           "💰 Saldo: {$currencySymbol}" . number_format($newBalance, 0, ',', '.');

                $this->sendMessage($from, $message);
            } catch (\Exception $e) {
                Log::error('WAHA: Error creating expense', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $this->sendMessage($from, "❌ Gagal menyimpan transaksi: " . $e->getMessage());
            }
        } elseif ($buttonId === 'cancel_receipt') {
            cache()->forget("whatsapp_receipt_{$from}");
            $this->sendMessage($from, "❌ Pengeluaran dibatalkan.");
        } elseif ($buttonId === 'confirm_transaction') {
            $pending = cache()->get("pending_transaction_{$from}");
            
            if (!$pending) {
                $this->sendMessage($from, "❌ Data transaksi sudah kadaluarsa. Silakan kirim ulang.");
                return;
            }
            
            $user = User::find($pending['user_id']);
            $asset = Asset::find($pending['asset_id']);
            $parsed = $pending['parsed'];
            
            // Override currency with asset currency to ensure consistency
            $parsed['currency'] = $asset->currency;
            
            // Create transaction
            $this->createTransaction($user, $asset, $parsed, $from);
            
            // Clear cache
            cache()->forget("pending_transaction_{$from}");
        } elseif ($buttonId === 'cancel_transaction') {
            cache()->forget("pending_transaction_{$from}");
            $this->sendMessage($from, "❌ Transaksi dibatalkan.");
        }
    }

    /**
     * Download media from WAHA using the mediaUrl from webhook payload
     */
    private function downloadMedia(string $mediaUrl): ?string
    {
        // ... (downloadMedia implementation) ...
        try {
            // WAHA returns internal container URL (e.g. localhost:3000)
            // We need to replace it with our configured base URL (e.g. localhost:3001)
            // to handle Docker port mappings correctly
            if ($this->baseUrl) {
                $parsedUrl = parse_url($mediaUrl);
                $path = $parsedUrl['path'] ?? '';
                $query = $parsedUrl['query'] ?? '';
                
                // Reconstruct URL with configured base URL
                $mediaUrl = "{$this->baseUrl}{$path}";
                if ($query) {
                    $mediaUrl .= "?{$query}";
                }
            }

            // The mediaUrl already includes session parameter from WAHA
            $response = $this->getHttpClient()->get($mediaUrl);

            if (!$response->successful()) {
                Log::error('WAHA: Failed to download media', [
                    'media_url' => $mediaUrl,
                    'status_code' => $response->status(),
                    'response_body' => substr($response->body(), 0, 500) // Limit to 500 chars
                ]);
                return null;
            }

            // Save to temp file with unique name
            $tempPath = storage_path('app/temp_' . uniqid('media_') . '.jpg');
            file_put_contents($tempPath, $response->body());

            return $tempPath;
        } catch (\Exception $e) {
            Log::error('WAHA: Error downloading media', [
                'error' => $e->getMessage(),
                'media_url' => $mediaUrl
            ]);

            return null;
        }
    }

    /**
     * Handle text replies for pending actions (fallback for buttons)
     */
    private function handlePendingAction(string $text, User $user, string $from): bool
    {
        // Check for conversation state
        if (ConversationState::hasActive($from)) {
            return $this->handleConversationState($text, $user, $from);
        }
        
        // Check for receipt confirmation
        if (cache()->has("whatsapp_receipt_{$from}")) {
            if (in_array($normalizedText, ['1', 'ya', 'yes', 'y', 'simpan'])) {
                $this->handleButtonReply('confirm_receipt', $user, $from);
                return true;
            }
            if (in_array($normalizedText, ['2', 'tidak', 'no', 'n', 'batal'])) {
                $this->handleButtonReply('cancel_receipt', $user, $from);
                return true;
            }
        }
        
        // Check for transaction confirmation
        if (cache()->has("pending_transaction_{$from}")) {
            if (in_array($normalizedText, ['1', 'ya', 'yes', 'y', 'benar'])) {
                $this->handleButtonReply('confirm_transaction', $user, $from);
                return true;
            }
            if (in_array($normalizedText, ['2', 'tidak', 'no', 'n', 'batal'])) {
                $this->handleButtonReply('cancel_transaction', $user, $from);
                return true;
            }
        }
        
        return false;
    }

    /**
     * Handle transaction history command
     */
    private function handleHistoryCommand(User $user, string $from, ?string $filter = null): void
    {
        $query = $user->personalAssets()
            ->with(['incomes' => function($q) {
                $q->orderBy('date', 'desc')->limit(10);
            }, 'expenses' => function($q) {
                $q->orderBy('date', 'desc')->limit(10);
            }]);

        // Apply filter if provided
        if ($filter) {
            $upperFilter = strtoupper($filter);
            if ($upperFilter === 'JPY' || $upperFilter === 'IDR') {
                $query->where('currency', $upperFilter);
            } else {
                $query->where('name', 'LIKE', "%{$filter}%");
            }
        }

        $assets = $query->get();

        // Collect all transactions
        $transactions = collect();
        foreach ($assets as $asset) {
            foreach ($asset->incomes as $income) {
                $transactions->push([
                    'date' => $income->date,
                    'type' => 'income',
                    'amount' => $income->amount,
                    'currency' => $asset->currency,
                    'category' => $income->category,
                    'asset_name' => $asset->name,
                ]);
            }
            foreach ($asset->expenses as $expense) {
                $transactions->push([
                    'date' => $expense->date,
                    'type' => 'expense',
                    'amount' => $expense->amount,
                    'currency' => $asset->currency,
                    'category' => $expense->category,
                    'asset_name' => $asset->name,
                ]);
            }
        }

        // Sort by date and take 5
        $transactions = $transactions->sortByDesc('date')->take(5);

        if ($transactions->isEmpty()) {
            $filterText = $filter ? " untuk {$filter}" : "";
            $this->sendMessage($from, "📝 Belum ada transaksi{$filterText}.");
            return;
        }

        $filterText = $filter ? " ({$filter})" : "";
        $message = "📝 *Riwayat Transaksi{$filterText}*\n\n";

        $num = 1;
        foreach ($transactions as $t) {
            $date = \Carbon\Carbon::parse($t['date'])->format('d M');
            $icon = $t['type'] === 'income' ? '↙️' : '↗️';
            $currencySymbol = $t['currency'] === 'JPY' ? '¥' : 'Rp';
            $amount = number_format($t['amount'], 0, ',', '.');
            
            $message .= "{$num}. {$date} - {$t['category']} {$currencySymbol}{$amount} ({$t['asset_name']}) {$icon}\n";
            $num++;
        }

        $message .= "\nLihat lebih: " . config('app.frontend_url') . "/dashboard/transactions";
        $this->sendMessage($from, $message);
    }

    /**
     * Handle summary statistics command
     */
    private function handleSummaryCommand(User $user, string $from): void
    {
        $currentMonth = now()->format('Y-m');
        $assets = $user->personalAssets()->get();
        
        $totalJPY = $assets->where('currency', 'JPY')->sum('balance');
        $totalIDR = $assets->where('currency', 'IDR')->sum('balance');
        
        $incomeJPY = Income::whereHas('asset', function($q) use ($user) {
            $q->where('owner_id', $user->id)->where('currency', 'JPY');
        })->whereRaw("DATE_FORMAT(date, '%Y-%m') = ?", [$currentMonth])->sum('amount');
        
        $incomeIDR = Income::whereHas('asset', function($q) use ($user) {
            $q->where('owner_id', $user->id)->where('currency', 'IDR');
        })->whereRaw("DATE_FORMAT(date, '%Y-%m') = ?", [$currentMonth])->sum('amount');
        
        $expenseJPY = Expense::whereHas('asset', function($q) use ($user) {
            $q->where('owner_id', $user->id)->where('currency', 'JPY');
        })->whereRaw("DATE_FORMAT(date, '%Y-%m') = ?", [$currentMonth])->sum('amount');
        
        $expenseIDR = Expense::whereHas('asset', function($q) use ($user) {
            $q->where('owner_id', $user->id)->where('currency', 'IDR');
        })->whereRaw("DATE_FORMAT(date, '%Y-%m') = ?", [$currentMonth])->sum('amount');
        
        $balanceJPY = $incomeJPY - $expenseJPY;
        $balanceIDR = $incomeIDR - $expenseIDR;
        
        $topCategory = Expense::whereHas('asset', function($q) use ($user) {
            $q->where('owner_id', $user->id);
        })->whereRaw("DATE_FORMAT(date, '%Y-%m') = ?", [$currentMonth])
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->first();
        
        $monthName = now()->format('F Y');
        
        $message = "📊 *Ringkasan Bulan Ini ({$monthName})*\n\n";
        $message .= "💰 *Total Aset:*\n";
        $message .= "   JPY: ¥" . number_format($totalJPY, 0, ',', '.') . "\n";
        $message .= "   IDR: Rp" . number_format($totalIDR, 0, ',', '.') . "\n\n";
        $message .= "📈 *Pemasukan:*\n";
        $message .= "   JPY: ¥" . number_format($incomeJPY, 0, ',', '.') . "\n";
        $message .= "   IDR: Rp" . number_format($incomeIDR, 0, ',', '.') . "\n\n";
        $message .= "📉 *Pengeluaran:*\n";
        $message .= "   JPY: ¥" . number_format($expenseJPY, 0, ',', '.') . "\n";
        $message .= "   IDR: Rp" . number_format($expenseIDR, 0, ',', '.') . "\n\n";
        $message .= "💵 *Balance:*\n";
        $balanceIconJPY = $balanceJPY >= 0 ? '+' : '';
        $balanceIconIDR = $balanceIDR >= 0 ? '+' : '';
        $message .= "   JPY: {$balanceIconJPY}¥" . number_format($balanceJPY, 0, ',', '.') . "\n";
        $message .= "   IDR: {$balanceIconIDR}Rp" . number_format($balanceIDR, 0, ',', '.') . "\n";
        
        if ($topCategory) {
            $message .= "\n🏆 *Kategori Terbesar:* {$topCategory->category}";
        }
        
        $message .= "\n\nDetail: " . config('app.frontend_url') . "/dashboard";
        $this->sendMessage($from, $message);
    }

    /**
     * Handle export command
     */
    private function handleExportCommand(User $user, string $from): void
    {
        ConversationState::set($from, 'export_period');
        
        $message = "📄 *Pilih periode export:*\n\n";
        $message .= "1. Bulan ini\n";
        $message .= "2. 3 bulan terakhir\n";
        $message .= "3. 6 bulan terakhir\n";
        $message .= "4. Tahun ini\n\n";
        $message .= "Ketik angka 1-4";
        
        $this->sendMessage($from, $message);
    }
    /**
     * Handle asset creation start
     */
    private function handleAssetCreationStart(User $user, string $from): void
    {
        ConversationState::set($from, 'asset_creation_type');
        
        $message = "🏦 *Tambah Aset Baru*\n\n";
        $message .= "Jenis aset? (ketik angka)\n\n";
        $message .= "1. Tabungan\n";
        $message .= "2. E-Money\n";
        $message .= "3. Investasi\n";
        $message .= "4. Cash\n\n";
        $message .= "Atau ketik 'batal' untuk membatalkan";
        
        $this->sendMessage($from, $message);
    }

    /**
     * Handle asset edit start
     */
    private function handleAssetEditStart(User $user, string $from, string $assetName): void
    {
        $asset = $user->personalAssets()
            ->where('name', 'LIKE', "%{$assetName}%")
            ->first();
        
        if (!$asset) {
            $this->sendMessage($from, "❌ Aset '{$assetName}' tidak ditemukan.\n\nCek daftar aset dengan perintah: saldo");
            return;
        }
        
        ConversationState::set($from, 'asset_edit_choice', ['asset_id' => $asset->id]);
        
        $message = "✏️ *Edit Aset: {$asset->name}*\n\n";
        $message .= "Apa yang ingin diubah?\n\n";
        $message .= "1. Nama\n";
        $message .= "2. Saldo\n";
        $message .= "3. Batal\n\n";
        $message .= "Ketik angka 1-3";
        
        $this->sendMessage($from, $message);
    }

    /**
     * Handle asset delete start
     */
    private function handleAssetDeleteStart(User $user, string $from, string $assetName): void
    {
        $asset = $user->personalAssets()
            ->where('name', 'LIKE', "%{$assetName}%")
            ->first();
        
        if (!$asset) {
            $this->sendMessage($from, "❌ Aset '{$assetName}' tidak ditemukan.\n\nCek daftar aset dengan perintah: saldo");
            return;
        }
        
        // Check if it's a primary wallet
        if ($asset->id === $user->primary_asset_jpy_id || $asset->id === $user->primary_asset_idr_id) {
            $this->sendMessage($from, "⚠️ Tidak bisa menghapus dompet utama.\n\nUbah dompet utama dulu dengan perintah: /dompet");
            return;
        }
        
        ConversationState::set($from, 'asset_delete_confirm', ['asset_id' => $asset->id]);
        
        $balance = number_format($asset->balance, 0, ',', '.');
        $currencySymbol = $asset->currency === 'JPY' ? '¥' : 'Rp';
        
        $message = "⚠️ *Hapus Aset: {$asset->name}*\n\n";
        $message .= "Saldo: {$currencySymbol}{$balance}\n\n";
        $message .= "Yakin ingin menghapus aset ini?\n\n";
        $message .= "Ketik 'ya' untuk konfirmasi atau 'batal'";
        
        $this->sendMessage($from, $message);
    }

    /**
     * Handle conversation state for multi-step flows
     */
    private function handleConversationState(string $text, User $user, string $from): bool
    {
        $state = ConversationState::get($from);
        if (!$state) {
            return false;
        }
        
        $step = $state['step'];
        $data = $state['data'] ?? [];
        $normalizedText = strtolower(trim($text));
        
        // Handle cancellation
        if (in_array($normalizedText, ['batal', 'cancel', 'stop'])) {
            ConversationState::clear($from);
            $this->sendMessage($from, "✅ Dibatalkan.");
            return true;
        }
        
        // Export flow
        if ($step === 'export_period') {
            return $this->handleExportPeriodSelection($text, $user, $from);
        }
        
        // Asset creation flow
        if (str_starts_with($step, 'asset_creation_')) {
            return $this->handleAssetCreationFlow($text, $user, $from, $step, $data);
        }
        
        // Asset edit flow
        if (str_starts_with($step, 'asset_edit_')) {
            return $this->handleAssetEditFlow($text, $user, $from, $step, $data);
        }
        
        // Asset delete flow
        if ($step === 'asset_delete_confirm') {
            return $this->handleAssetDeleteConfirm($text, $user, $from, $data);
        }
        
        // Primary wallet change flow
        if (str_starts_with($step, 'primary_wallet_')) {
            return $this->handlePrimaryWalletFlow($text, $user, $from, $step, $data);
        }
        
        return false;
    }

    /**
     * Handle export period selection
     */
    private function handleExportPeriodSelection(string $text, User $user, string $from): bool
    {
        $choice = trim($text);
        
        if (!in_array($choice, ['1', '2', '3', '4'])) {
            $this->sendMessage($from, "❌ Pilihan tidak valid. Ketik angka 1-4 atau 'batal'");
            return true;
        }
        
        $periods = [
            '1' => ['name' => 'Bulan ini', 'months' => 1],
            '2' => ['name' => '3 bulan terakhir', 'months' => 3],
            '3' => ['name' => '6 bulan terakhir', 'months' => 6],
            '4' => ['name' => 'Tahun ini', 'months' => 12],
        ];
        
        $period = $periods[$choice];
        ConversationState::clear($from);
        
        $this->sendMessage($from, "⏳ Membuat laporan {$period['name']}...");
        
        try {
            $reportService = app(\App\Services\ReportService::class);
            $startDate = now()->subMonths($period['months'])->startOfMonth();
            $endDate = now()->endOfMonth();
            
            // Generate report for both currencies
            $reportDataJPY = $reportService->generateReport($user->id, $startDate, $endDate, 'JPY');
            $reportDataIDR = $reportService->generateReport($user->id, $startDate, $endDate, 'IDR');
            
            $filename = "Laporan_{$period['name']}_" . now()->format('Y-m-d') . ".xlsx";
            $filepath = "exports/{$user->id}/" . uniqid() . ".xlsx";
            
            // Combine both currency reports
            $combinedData = [
                'transactions' => $reportDataJPY['transactions']->concat($reportDataIDR['transactions'])->sortByDesc('date')->values(),
                'assets' => $reportDataJPY['assets']->concat($reportDataIDR['assets']),
                'totals' => [
                    'total_income' => $reportDataJPY['totals']['total_income'] + $reportDataIDR['totals']['total_income'],
                    'total_expense' => $reportDataJPY['totals']['total_expense'] + $reportDataIDR['totals']['total_expense'],
                    'balance' => $reportDataJPY['totals']['balance'] + $reportDataIDR['totals']['balance'],
                ],
                'period' => $reportDataJPY['period'],
                'currency' => 'ALL',
            ];
            
            $export = new \App\Exports\FinancialReportExport($combinedData);
            \Maatwebsite\Excel\Facades\Excel::store($export, $filepath, 'local');
            
            // Create export record for download link
            $token = \Illuminate\Support\Str::uuid();
            \App\Models\Export::create([
                'user_id' => $user->id,
                'token' => $token,
                'filename' => $filename,
                'filepath' => $filepath,
                'period' => $period['name'],
                'expires_at' => now()->addHours(24),
            ]);
            
            // Send file directly via WhatsApp
            $this->sendDocument($from, $filepath, $filename);
            
        } catch (\Exception $e) {
            \Log::error('Export failed', ['error' => $e->getMessage()]);
            $this->sendMessage($from, "❌ Gagal membuat laporan.");
        }
        
        return true;
    }

    private function handleAssetCreationFlow(string $text, User $user, string $from, string $step, array $data): bool
    {
        $choice = trim($text);
        
        if ($step === 'asset_creation_type') {
            $types = ['1' => 'tabungan', '2' => 'e-money', '3' => 'investasi', '4' => 'cash'];
            if (!isset($types[$choice])) {
                $this->sendMessage($from, "❌ Pilihan tidak valid. Ketik angka 1-4");
                return true;
            }
            $data['type'] = $types[$choice];
            ConversationState::set($from, 'asset_creation_country', $data);
            $this->sendMessage($from, "Negara?\n\n1. Jepang (JPY)\n2. Indonesia (IDR)");
            return true;
        }
        
        if ($step === 'asset_creation_country') {
            $countries = ['1' => ['country' => 'JP', 'currency' => 'JPY'], '2' => ['country' => 'ID', 'currency' => 'IDR']];
            if (!isset($countries[$choice])) {
                $this->sendMessage($from, "❌ Pilihan tidak valid.");
                return true;
            }
            $data = array_merge($data, $countries[$choice]);
            ConversationState::set($from, 'asset_creation_name', $data);
            $this->sendMessage($from, "Nama aset?");
            return true;
        }
        
        if ($step === 'asset_creation_name') {
            $data['name'] = $text;
            ConversationState::set($from, 'asset_creation_balance', $data);
            $this->sendMessage($from, "Saldo awal?");
            return true;
        }
        
        if ($step === 'asset_creation_balance') {
            $balance = preg_replace('/[^0-9]/', '', $text);
            if (empty($balance)) {
                $this->sendMessage($from, "❌ Saldo tidak valid.");
                return true;
            }
            
            \App\Models\Asset::create([
                'owner_id' => $user->id,
                'name' => $data['name'],
                'type' => $data['type'],
                'country' => $data['country'],
                'currency' => $data['currency'],
                'balance' => $balance,
            ]);
            
            ConversationState::clear($from);
            $currencySymbol = $data['currency'] === 'JPY' ? '¥' : 'Rp';
            $this->sendMessage($from, "✅ Aset *{$data['name']}* berhasil ditambahkan dengan saldo {$currencySymbol}" . number_format($balance, 0, ',', '.'));
            return true;
        }
        
        return false;
    }

    private function handleAssetEditFlow(string $text, User $user, string $from, string $step, array $data): bool
    {
        if ($step === 'asset_edit_choice') {
            $choice = trim($text);
            if ($choice === '3') {
                ConversationState::clear($from);
                $this->sendMessage($from, "✅ Dibatalkan.");
                return true;
            }
            if (!in_array($choice, ['1', '2'])) {
                $this->sendMessage($from, "❌ Pilihan tidak valid.");
                return true;
            }
            if ($choice === '1') {
                ConversationState::set($from, 'asset_edit_name', $data);
                $this->sendMessage($from, "Nama baru?");
            } else {
                ConversationState::set($from, 'asset_edit_balance', $data);
                $this->sendMessage($from, "Saldo baru?");
            }
            return true;
        }
        
        if ($step === 'asset_edit_name') {
            $asset = \App\Models\Asset::find($data['asset_id']);
            if ($asset && $asset->owner_id === $user->id) {
                $asset->update(['name' => $text]);
                ConversationState::clear($from);
                $this->sendMessage($from, "✅ Nama aset diubah menjadi *{$text}*");
            }
            return true;
        }
        
        if ($step === 'asset_edit_balance') {
            $balance = preg_replace('/[^0-9]/', '', $text);
            $asset = \App\Models\Asset::find($data['asset_id']);
            if ($asset && $asset->owner_id === $user->id) {
                $asset->update(['balance' => $balance]);
                ConversationState::clear($from);
                $currencySymbol = $asset->currency === 'JPY' ? '¥' : 'Rp';
                $this->sendMessage($from, "✅ Saldo diubah menjadi {$currencySymbol}" . number_format($balance, 0, ',', '.'));
            }
            return true;
        }
        
        return false;
    }

    private function handleAssetDeleteConfirm(string $text, User $user, string $from, array $data): bool
    {
        if (!in_array(strtolower(trim($text)), ['ya', 'yes', 'y'])) {
            ConversationState::clear($from);
            $this->sendMessage($from, "✅ Dibatalkan.");
            return true;
        }
        
        $asset = \App\Models\Asset::find($data['asset_id']);
        if ($asset && $asset->owner_id === $user->id) {
            $name = $asset->name;
            $asset->delete();
            ConversationState::clear($from);
            $this->sendMessage($from, "✅ Aset *{$name}* berhasil dihapus");
        }
        return true;
    }

    private function handlePrimaryWalletFlow(string $text, User $user, string $from, string $step, array $data): bool
    {
        ConversationState::clear($from);
        $this->sendMessage($from, "Fitur ini sedang dalam pengembangan.");
        return true;
    }
}
