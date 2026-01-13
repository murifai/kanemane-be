<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    protected $fillable = [
        'user_id',
        'currency',
        'amount',
        'month',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'month' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
