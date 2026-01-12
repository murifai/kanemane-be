<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private string $apiKey;
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
    }

    /**
     * Parse natural language transaction text to structured data
     * Enhanced to detect income/expense, asset, and currency
     *
     * @param string $text Example: "jajan crepes 500 yen pake PayPay" or "gajian 5000000 masuk BCA"
     * @param array $userAssets List of user's assets for matching
     * @return array ['type' => 'expense', 'category' => '食費', 'amount' => 500, 'currency' => 'JPY', 'asset_name' => 'PayPay', 'description' => 'jajan crepes']
     */
    public function parseTransaction(string $text, array $userAssets = []): array
    {
        // Build asset names list for prompt
        $assetNames = array_map(fn($asset) => $asset['name'], $userAssets);
        $assetNamesStr = empty($assetNames) ? 'None' : implode(', ', $assetNames);
        
        $prompt = "Parse this transaction text into JSON format. Identify:
1. Transaction type: 'income' or 'expense'
2. Category (must be one of: 食費, 交通費, 家賃, 光熱費, その他)
3. Amount (number only)
4. Currency: 'JPY' or 'IDR' (detect from keywords: yen/円/jpy = JPY, rupiah/rp/idr = IDR)
5. Asset name (if mentioned, must match one from user's assets)
6. Description

Rules:
- Transaction Type Detection:
  * INCOME keywords: gaji, gajian, salary, beasiswa, scholarship, bonus, dapat, terima, masuk, income, pendapatan
  * EXPENSE keywords: jajan, beli, belanja, bayar, shopping, makan, transport, sewa, pay, spend
  * Default to 'expense' if unclear

- Category Rules:
  * 食費 (Food): makan, jajan, food, meal, breakfast, lunch, dinner, snack, restaurant, cafe, grocery
  * 交通費 (Transport): transport, train, bus, taxi, kereta, grab, gojek, bensin, gas, parking
  * 家賃 (Rent): rent, sewa, housing, apartment
  * 光熱費 (Utilities): electric, listrik, water, air, internet, wifi, gas, utility
  * その他 (Others): everything else

- Currency Detection:
  * JPY: yen, 円, jpy (case insensitive)
  * IDR: rupiah, rp, idr (case insensitive)
  * Default to JPY if not specified

- Asset Matching:
  * User's assets: {$assetNamesStr}
  * Match case-insensitively
  * Return null if not mentioned or no match

Input text: \"{$text}\"

Return ONLY valid JSON in this exact format:
{\"type\": \"expense\", \"category\": \"食費\", \"amount\": 500, \"currency\": \"JPY\", \"asset_name\": \"PayPay\", \"description\": \"jajan crepes\"}";

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/models/gemini-2.5-flash:generateContent?key={$this->apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ]
            ]);

            if (!$response->successful()) {
                Log::error('Gemini API error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new \Exception('Failed to parse text with AI');
            }

            $result = $response->json();
            $generatedText = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';

            // Extract JSON from response (might be wrapped in markdown code blocks)
            $generatedText = preg_replace('/```json\n?/', '', $generatedText);
            $generatedText = preg_replace('/```\n?/', '', $generatedText);
            $generatedText = trim($generatedText);

            $parsed = json_decode($generatedText, true);

            if (!$parsed || !isset($parsed['type'], $parsed['category'], $parsed['amount'])) {
                throw new \Exception('Invalid AI response format');
            }

            return [
                'type' => $parsed['type'], // 'income' or 'expense'
                'category' => $parsed['category'],
                'amount' => (float) $parsed['amount'],
                'currency' => $parsed['currency'] ?? 'JPY',
                'asset_name' => $parsed['asset_name'] ?? null,
                'description' => $parsed['description'] ?? '',
            ];
        } catch (\Exception $e) {
            Log::error('Error parsing transaction with Gemini', [
                'error' => $e->getMessage(),
                'text' => $text
            ]);

            // Fallback: simple parsing
            return $this->fallbackTransactionParser($text);
        }
    }

    /**
     * Legacy method for backward compatibility
     * @deprecated Use parseTransaction instead
     */
    public function parseText(string $text): array
    {
        $result = $this->parseTransaction($text, []);
        
        return [
            'category' => $result['category'],
            'amount' => $result['amount'],
            'description' => $result['description'],
        ];
    }

    /**
     * Scan receipt image using OCR
     *
     * @param string $imagePath Path to receipt image
     * @return array ['amount' => 1250, 'date' => '2026-01-11', 'merchant' => 'Lawson', 'items' => [...]]
     */
    public function scanReceipt(string $imagePath): array
    {
        if (!file_exists($imagePath)) {
            throw new \Exception('Image file not found');
        }

        $imageData = base64_encode(file_get_contents($imagePath));
        $mimeType = mime_content_type($imagePath);

        $prompt = "Analyze this Japanese receipt image and extract information in JSON format.

Extract:
- total_amount: Total purchase amount (number only)
- date: Transaction date (YYYY-MM-DD format, use today if not visible)
- merchant: Store/merchant name
- items: Array of purchased items with name and price
- category: Best matching category (食費, 交通費, 家賃, 光熱費, その他)

Return ONLY valid JSON in this exact format:
{
  \"total_amount\": 1250,
  \"date\": \"2026-01-11\",
  \"merchant\": \"Lawson\",
  \"category\": \"食費\",
  \"items\": [
    {\"name\": \"Onigiri\", \"price\": 150},
    {\"name\": \"Drink\", \"price\": 100}
  ]
}";

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/models/gemini-2.5-flash:generateContent?key={$this->apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                            [
                                'inline_data' => [
                                    'mime_type' => $mimeType,
                                    'data' => $imageData
                                ]
                            ]
                        ]
                    ]
                ]
            ]);

            if (!$response->successful()) {
                Log::error('Gemini Vision API error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new \Exception('Failed to scan receipt with AI');
            }

            $result = $response->json();
            $generatedText = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';

            // Extract JSON from response
            $generatedText = preg_replace('/```json\n?/', '', $generatedText);
            $generatedText = preg_replace('/```\n?/', '', $generatedText);
            $generatedText = trim($generatedText);

            $parsed = json_decode($generatedText, true);

            if (!$parsed || !isset($parsed['total_amount'])) {
                throw new \Exception('Invalid AI response format');
            }

            return [
                'amount' => (float) $parsed['total_amount'],
                'date' => $parsed['date'] ?? now()->format('Y-m-d'),
                'merchant' => $parsed['merchant'] ?? 'Unknown',
                'category' => $parsed['category'] ?? 'その他',
                'items' => $parsed['items'] ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('Error scanning receipt with Gemini', [
                'error' => $e->getMessage(),
                'image' => $imagePath
            ]);

            throw $e;
        }
    }

    /**
     * Fallback parser for transactions when AI fails
     */
    private function fallbackTransactionParser(string $text): array
    {
        // Extract amount (any number in the text)
        preg_match('/\d+/', $text, $matches);
        $amount = isset($matches[0]) ? (float) $matches[0] : 0;

        // Detect transaction type
        $type = 'expense'; // default
        if (preg_match('/gaji|gajian|salary|beasiswa|scholarship|bonus|dapat|terima|masuk|income|pendapatan/i', $text)) {
            $type = 'income';
        }

        // Detect currency
        $currency = 'JPY'; // default
        if (preg_match('/rupiah|rp|idr/i', $text)) {
            $currency = 'IDR';
        }

        // Simple category detection
        $category = 'その他';
        if (preg_match('/makan|jajan|food|resto|cafe|breakfast|lunch|dinner|食|飯/i', $text)) {
            $category = '食費';
        } elseif (preg_match('/train|bus|taxi|transport|交通|kereta|grab|gojek/i', $text)) {
            $category = '交通費';
        } elseif (preg_match('/rent|sewa|家賃/i', $text)) {
            $category = '家賃';
        } elseif (preg_match('/electric|water|internet|gas|光熱費|listrik|air|wifi/i', $text)) {
            $category = '光熱費';
        }

        return [
            'type' => $type,
            'category' => $category,
            'amount' => $amount,
            'currency' => $currency,
            'asset_name' => null,
            'description' => $text,
        ];
    }

    /**
     * Legacy fallback parser for backward compatibility
     * @deprecated Use fallbackTransactionParser instead
     */
    private function fallbackParser(string $text): array
    {
        $result = $this->fallbackTransactionParser($text);
        
        return [
            'category' => $result['category'],
            'amount' => $result['amount'],
            'description' => $result['description'],
        ];
    }
}
