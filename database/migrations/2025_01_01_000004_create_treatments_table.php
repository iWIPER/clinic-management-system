<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('nome');
            $table->string('especialidade')->nullable();
            $table->integer('duracao_padrao')->default(30); // minutes
            $table->decimal('preco_base', 10, 2)->default(0);
            $table->text('descricao')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatments');
    }
};
