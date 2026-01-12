<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('test:gemini', function () {
    $this->info('🔍 Testing Gemini API Connection...');
    $this->newLine();

    $apiKey = config('services.gemini.api_key');

    if (!$apiKey) {
        $this->error('❌ GEMINI_API_KEY not configured!');
        $this->info('Add GEMINI_API_KEY to your .env file');
        return 1;
    }

    $this->info('API Key: ' . substr($apiKey, 0, 20) . '...');
    $this->newLine();

    // Test 1: Text Parsing
    $this->info('Test 1: Text Parsing API');
    $this->line('------------------------');

    try {
        $geminiService = app(\App\Services\GeminiService::class);
        $result = $geminiService->parseText('makan siang 850');

        $this->info('✅ Text Parsing API working!');
        $this->line('Category: ' . $result['category']);
        $this->line('Amount: ' . $result['amount']);
        $this->line('Description: ' . $result['description']);
    } catch (\Exception $e) {
        $this->error('❌ Text Parsing failed: ' . $e->getMessage());
    }

    // Test 2: Vision API (Receipt Scanning)
    $this->newLine();
    $this->info('Test 2: Vision API (Receipt Scanning)');
    $this->line('-------------------------------------');

    $testImagePath = storage_path('app/receipts/test-receipt.png');

    if (!file_exists($testImagePath)) {
        $this->warn('⚠️  Test image not found at: ' . $testImagePath);
        $this->info('You can test Vision API by uploading a receipt through the webapp');
    } else {
        try {
            $geminiService = app(\App\Services\GeminiService::class);
            $result = $geminiService->scanReceipt($testImagePath);

            $this->info('✅ Vision API working!');
            $this->line('Amount: ¥' . $result['amount']);
            $this->line('Date: ' . $result['date']);
            $this->line('Merchant: ' . $result['merchant']);
            $this->line('Category: ' . $result['category']);
            $this->line('Items: ' . count($result['items']) . ' items');
        } catch (\Exception $e) {
            $this->error('❌ Vision API failed: ' . $e->getMessage());
            $this->line('This might be expected if the test image is not a receipt');
        }
    }

    $this->newLine();
    $this->info('======================');
    $this->info('Test Complete');
    $this->info('======================');
    $this->newLine();
    $this->info('💡 Tips:');
    $this->line('• Text Parsing API is used for quick expense entry');
    $this->line('• Vision API is used for receipt scanning');
    $this->line('• Both features are available in the webapp');

    return 0;
})->purpose('Test Gemini API connection');
