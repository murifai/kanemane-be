<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            // owner_type: 'App\Models\User' or 'App\Models\Family'
            // But usually we use polymorphic relation or just string
            $table->string('owner_type'); 
            $table->unsignedBigInteger('owner_id');
            $table->string('country'); // 'JP' or 'ID'
            $table->string('name');
            $table->string('type'); // 'tabungan', 'e-money', 'investasi'
            $table->string('currency'); // 'JPY', 'IDR'
            $table->decimal('balance', 15, 2)->default(0);
            $table->timestamps();
            
            $table->index(['owner_type', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
