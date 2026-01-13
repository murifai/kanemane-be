<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AIController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\ReportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::prefix('auth')->group(function () {
    Route::get('google/redirect', [AuthController::class, 'redirectToGoogle']);
    Route::get('google/callback', [AuthController::class, 'handleGoogleCallback']);
});

// Webhook routes (no auth required)
Route::prefix('webhook')->group(function () {
    Route::get('whatsapp', [WebhookController::class, 'verifyWhatsApp']);
    Route::post('whatsapp', [WebhookController::class, 'receiveWhatsApp']);
    Route::post('midtrans', [SubscriptionController::class, 'handleNotification']);
});

// Onboarding token generation (no auth required, for WhatsApp bot)
Route::post('onboarding/generate-token', [\App\Http\Controllers\OnboardingController::class, 'generateToken']);

// Public export download (no auth required, token-based)
Route::get('exports/{token}', [\App\Http\Controllers\ExportController::class, 'download']);



// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::get('auth/user', [AuthController::class, 'user']);
    Route::put('auth/profile', [AuthController::class, 'updateProfile']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    
    // User
    Route::get('user/profile', [\App\Http\Controllers\UserController::class, 'profile']);
    Route::post('user/primary-asset', [\App\Http\Controllers\UserController::class, 'setPrimaryAsset']);
    
    // Onboarding
    Route::get('onboarding/check', [\App\Http\Controllers\OnboardingController::class, 'checkStatus']);
    Route::post('onboarding/verify-token', [\App\Http\Controllers\OnboardingController::class, 'verifyToken']);
    Route::post('onboarding/complete', [\App\Http\Controllers\OnboardingController::class, 'complete']);
    
    
    // Assets
    Route::apiResource('assets', AssetController::class);
    
    // Transactions
    Route::get('transactions', [TransactionController::class, 'index']);
    Route::post('transactions/income', [TransactionController::class, 'storeIncome']);
    Route::post('transactions/expense', [TransactionController::class, 'storeExpense']);
    Route::get('transactions/{id}', [TransactionController::class, 'show']);
    Route::put('transactions/{id}', [TransactionController::class, 'update']);
    Route::delete('transactions/{id}', [TransactionController::class, 'destroy']);
    
    // Dashboard
    Route::get('dashboard/summary', [DashboardController::class, 'summary']);
    Route::get('dashboard/charts', [DashboardController::class, 'charts']);

    // AI
    Route::post('ai/parse-text', [AIController::class, 'parseText']);
    Route::post('ai/scan-receipt', [AIController::class, 'scanReceipt']);
    Route::post('ai/quick-expense', [AIController::class, 'quickExpense']);

    // Subscription
    Route::get('subscription', [SubscriptionController::class, 'index']);
    Route::get('subscription/plans', [SubscriptionController::class, 'plans']);
    Route::post('subscription/checkout', [SubscriptionController::class, 'checkout']);
    Route::get('subscription/status/{orderId}', [SubscriptionController::class, 'checkStatus']);
    Route::post('subscription/cancel', [SubscriptionController::class, 'cancel']);

    // Reports
    Route::get('reports/export', [ReportController::class, 'export']);
});
