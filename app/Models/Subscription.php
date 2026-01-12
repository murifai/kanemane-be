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
            'manual' => 10000,
            'ai' => 20000,
            'family_ai' => 30000,
            default => 0,
        };
    }

    /**
     * Get plan features
     */
    public static function getPlanFeatures(string $plan): array
    {
        return match($plan) {
            'manual' => [
                'Basic expense tracking',
                'Charts and reports',
                'Family account (up to 2 members)',
                'Manual data entry'
            ],
            'ai' => [
                'All Manual features',
                'Receipt OCR scanning',
                'Smart expense parsing',
                'WhatsApp chatbot',
                'Natural language input'
            ],
            'family_ai' => [
                'All AI features',
                'Extended family account (up to 4 members)',
                'Shared budget management',
                'Priority support'
            ],
            default => [],
        };
    }
}
