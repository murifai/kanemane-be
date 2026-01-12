<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Expense extends Model
{
    use HasFactory;
    protected $fillable = [
        'owner_type',
        'owner_id',
        'asset_id',
        'category',
        'amount',
        'currency',
        'date',
        'note',
        'created_by',
    ];
    
    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
    ];
    
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }
    
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
    
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }
}
