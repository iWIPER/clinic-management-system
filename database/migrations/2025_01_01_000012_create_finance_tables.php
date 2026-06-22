<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->decimal('salario_desejado', 10, 2)->default(0);
            $table->decimal('horas_trabalhadas', 5, 2)->default(160);
            $table->decimal('custos_fixos', 10, 2)->default(0);
            $table->decimal('margem_lucro', 5, 2)->default(30);
            $table->timestamps();
        });

        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('rascunho'); // rascunho, aprovado, rejeitado
            $table->decimal('total', 10, 2)->default(0);
            $table->date('valid_until')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tipo'); // receita, despesa
            $table->decimal('valor', 10, 2);
            $table->string('categoria');
            $table->string('descricao')->nullable();
            $table->string('origem_type')->nullable();
            $table->unsignedBigInteger('origem_id')->nullable();
            $table->string('caixa')->default('principal');
            $table->string('status')->default('pendente'); // pendente, pago, cancelado
            $table->date('vencimento')->nullable();
            $table->timestamp('pago_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('pricing_configs');
    }
};
