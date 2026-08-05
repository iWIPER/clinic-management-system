<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('convenios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('nome');
            $table->boolean('ativo')->default(true);
            $table->unsignedInteger('ordem')->default(0);
            $table->timestamps();

            $table->index(['clinic_id', 'ativo', 'ordem']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convenios');
    }
};
