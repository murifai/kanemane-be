<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan',
        'status',
        'started_at',
        'expires_at',
        'midtrans_order_id',
        'midtrans_transaction_id',
        'payment_type',
        'amount',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    /**
     * Relationship to User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if subscription is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active' &&
               $this->expires_at &&
               $this->expires_at->isFuture();
    }

    /**
     * Check if subscription is expired
     */
    public function isExpired(): bool
    {
        return $this->status === 'expired' ||
               ($this->expires_at && $this->expires_at->isPast());
    }

    /**
     * Get plan price
     */
    public static function getPlanPrice(string $plan): int
    {
        return match($plan) {
            'basic' => 19000,  // Rp 19.000/month
            'pro' => 49000,    // Rp 49.000/month
            default => 0,
        };
    }

    public static function getPlanFeatures(string $plan): array
    {
        return match($plan) {
            'basic' => [
                'Akses webapp (Dashboard, Assets, Transactions)',
                'Input transaksi manual',
                'Visualisasi grafik',
                'Limit 2 Aset per Mata Uang',
            ],
            'pro' => [
                'Semua fitur Basic',
                'Integrasi WhatsApp Bot',
                'Scan Foto Resi',
                'Laporan Keuangan Excel/CSV',
                'Unlimited Aset',
            ],
            default => [],
        };
    }

    /**
     * Check if subscription has access to a specific feature
     */
    public function hasFeature(string $feature): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        // Pro has access to all features
        if ($this->plan === 'pro') {
            return true;
        }

        // Basic tier restrictions
        $proOnlyFeatures = ['export', 'scan', 'whatsapp'];
        
        return !in_array($feature, $proOnlyFeatures);
    }

    /**
     * Check if can export reports
     */
    public function canExportReports(): bool
    {
        return $this->hasFeature('export');
    }

    /**
     * Check if can scan receipts
     */
    public function canScanReceipts(): bool
    {
        return $this->hasFeature('scan');
    }

    /**
     * Check if can use WhatsApp integration
     */
    public function canUseWhatsApp(): bool
    {
        return $this->hasFeature('whatsapp');
    }

}
