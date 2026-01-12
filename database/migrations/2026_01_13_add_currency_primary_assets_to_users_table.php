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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('primary_asset_jpy_id')
                ->nullable()
                ->after('primary_asset_id')
                ->constrained('assets')
                ->onDelete('set null');
                
            $table->foreignId('primary_asset_idr_id')
                ->nullable()
                ->after('primary_asset_jpy_id')
                ->constrained('assets')
                ->onDelete('set null');
        });
        
        // Migrate existing primary_asset_id to currency-specific fields
        DB::statement("
            UPDATE users u
            INNER JOIN assets a ON u.primary_asset_id = a.id
            SET u.primary_asset_jpy_id = a.id
            WHERE a.currency = 'JPY'
        ");
        
        DB::statement("
            UPDATE users u
            INNER JOIN assets a ON u.primary_asset_id = a.id
            SET u.primary_asset_idr_id = a.id
            WHERE a.currency = 'IDR'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['primary_asset_jpy_id']);
            $table->dropForeign(['primary_asset_idr_id']);
            $table->dropColumn(['primary_asset_jpy_id', 'primary_asset_idr_id']);
        });
    }
};
