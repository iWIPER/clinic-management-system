<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('nome');
            $table->string('sobrenome');
            $table->date('nascimento')->nullable();
            $table->string('status')->default('ativo'); // ativo, inativo, falecido
            $table->string('doc_tipo')->nullable(); // cpf, rg, passaporte
            $table->string('doc_numero')->nullable();
            $table->string('telefone')->nullable();
            $table->string('email')->nullable();
            $table->string('contato_emergencia_nome')->nullable();
            $table->string('contato_emergencia_telefone')->nullable();
            $table->string('cep')->nullable();
            $table->string('logradouro')->nullable();
            $table->string('numero')->nullable();
            $table->string('complemento')->nullable();
            $table->string('bairro')->nullable();
            $table->string('cidade')->nullable();
            $table->string('estado', 2)->nullable();
            $table->string('drive_folder_id')->nullable(); // Google Drive folder for this patient
            $table->text('observacoes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
