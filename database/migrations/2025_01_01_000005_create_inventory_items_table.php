<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('nome'); // material name
            $table->string('marca')->nullable();
            $table->string('lote')->nullable();
            $table->date('validade')->nullable();
            $table->decimal('custo_unitario', 10, 2)->default(0);
            $table->integer('quantidade')->default(0);
            $table->integer('quantidade_minima')->default(5);
            $table->string('local')->nullable();
            $table->string('condicao')->default('bom'); // bom, vencendo, vencido
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
