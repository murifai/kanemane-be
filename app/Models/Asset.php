<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Asset extends Model
{
    use HasFactory;
    protected $fillable = [
        'owner_type',
        'owner_id',
        'country',
        'name',
        'type',
        'currency',
        'balance',
    ];
    
    protected $casts = [
        'balance' => 'decimal:2',
    ];
    
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }
    
    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }
    
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
