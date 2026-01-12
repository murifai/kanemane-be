<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type'); 
            $table->unsignedBigInteger('owner_id');
            $table->foreignId('asset_id')->constrained()->onDelete('cascade');
            $table->string('category');
            $table->decimal('amount', 15, 2);
            $table->string('currency');
            $table->date('date');
            $table->text('note')->nullable();
            
            // Added created_by as agreed
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            
            $table->timestamps();
            
            $table->index(['owner_type', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
