<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Family extends Model
{
    protected $fillable = ['name'];
    
    public function members(): HasMany
    {
        return $this->hasMany(FamilyMember::class);
    }
    
    public function assets()
    {
        return $this->morphMany(Asset::class, 'owner');
    }
    
    public function incomes()
    {
        return $this->morphMany(Income::class, 'owner');
    }
    
    public function expenses()
    {
        return $this->morphMany(Expense::class, 'owner');
    }
}
