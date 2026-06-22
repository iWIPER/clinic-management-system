<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatment_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treatment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->integer('quantidade')->default(1);
            $table->timestamps();

            $table->unique(['treatment_id', 'inventory_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_materials');
    }
};
