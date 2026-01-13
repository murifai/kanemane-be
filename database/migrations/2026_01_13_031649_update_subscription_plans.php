<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, update existing data
        // Map: manual -> basic, ai -> pro, family_ai -> pro
        DB::table('subscriptions')
            ->where('plan', 'manual')
            ->update(['plan' => 'basic']);
            
        DB::table('subscriptions')
            ->whereIn('plan', ['ai', 'family_ai'])
            ->update(['plan' => 'pro']);

        // Then, alter the table to change the enum
        DB::statement("ALTER TABLE subscriptions MODIFY COLUMN plan ENUM('basic', 'pro') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse: basic -> manual, pro -> ai
        DB::statement("ALTER TABLE subscriptions MODIFY COLUMN plan ENUM('manual', 'ai', 'family_ai', 'basic', 'pro') NOT NULL");
        
        DB::table('subscriptions')
            ->where('plan', 'basic')
            ->update(['plan' => 'manual']);
            
        DB::table('subscriptions')
            ->where('plan', 'pro')
            ->update(['plan' => 'ai']);

        DB::statement("ALTER TABLE subscriptions MODIFY COLUMN plan ENUM('manual', 'ai', 'family_ai') NOT NULL");
    }
};
