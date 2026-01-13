<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'google_id',
        'avatar',
        'language',
        'primary_asset_id',
        'primary_asset_jpy_id',
        'primary_asset_idr_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];
    
    // Relationships
    public function familyMemberships()
    {
        return $this->hasMany(FamilyMember::class);
    }
    
    public function families()
    {
        return $this->belongsToMany(Family::class, 'family_members');
    }
    
    public function personalAssets()
    {
        return $this->morphMany(Asset::class, 'owner');
    }
    
    public function incomes()
    {
        return $this->morphMany(Income::class, 'owner');
    }
    
    public function primaryAsset()
    {
        return $this->belongsTo(Asset::class, 'primary_asset_id');
    }
    
    public function primaryAssetJpy()
    {
        return $this->belongsTo(Asset::class, 'primary_asset_jpy_id');
    }
    
    public function primaryAssetIdr()
    {
        return $this->belongsTo(Asset::class, 'primary_asset_idr_id');
    }
    
    public function getPrimaryAssetForCurrency(string $currency)
    {
        if ($currency === 'JPY') {
            return $this->primary_asset_jpy_id;
        } elseif ($currency === 'IDR') {
            return $this->primary_asset_idr_id;
        }
        return null;
    }
    
    public function expenses()
    {
        return $this->morphMany(Expense::class, 'owner');
    }
    
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }
    
    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class)
            ->where('status', 'active')
            ->latest();
    }
    
    /**
     * Check if user has an active subscription
     */
    public function hasActiveSubscription(): bool
    {
        $subscription = $this->activeSubscription()->first();
        return $subscription && $subscription->isActive();
    }
    
    /**
     * Get user's subscription tier
     */
    public function getSubscriptionTier(): string
    {
        $subscription = $this->activeSubscription()->first();
        
        if (!$subscription || !$subscription->isActive()) {
            return 'basic';
        }
        
        return $subscription->plan;
    }
    
    /**
     * Check if user can access a specific feature
     */
    public function canAccessFeature(string $feature): bool
    {
        $subscription = $this->activeSubscription()->first();
        
        if (!$subscription || !$subscription->isActive()) {
            return false;
        }
        
        return $subscription->hasFeature($feature);
    }

    /**
     * Check if user can create a new asset
     */
    public function canCreateAsset(string $currency): bool
    {
        $tier = $this->getSubscriptionTier();

        // Pro tier has no limits
        if ($tier === 'pro') {
            return true;
        }

        // Basic tier limit: 2 assets per currency
        if ($tier === 'basic') {
            $count = $this->personalAssets()
                ->where('currency', $currency)
                ->count();
            
            return $count < 2;
        }

        return false;
    }

}
