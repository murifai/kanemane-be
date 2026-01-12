<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type'); 
            $table->unsignedBigInteger('owner_id');
            $table->decimal('target_amount_yen', 15, 2);
            $table->integer('duration_month');
            $table->decimal('monthly_target', 15, 2);
            $table->date('start_date');
            $table->timestamps();
            
            $table->index(['owner_type', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};
