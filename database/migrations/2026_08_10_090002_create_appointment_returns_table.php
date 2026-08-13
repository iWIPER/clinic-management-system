<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Retornar em" do modal de agendamento — registra uma INTENÇÃO/pendência
     * de retorno, não altera nem duplica o agendamento atual e não cria um
     * novo Appointment sozinho. due_date já vem calculada (data da consulta +
     * intervalo escolhido, ou uma data customizada) — nenhuma lógica de
     * "1 mês" precisa ser refeita em outro lugar. Sem tela de gestão nesta
     * etapa: status existe só pra não fechar a porta pra isso depois.
     */
    public function up(): void
    {
        Schema::create('appointment_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('professional_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date');
            $table->text('reason')->nullable();
            $table->string('status')->default('pending'); // pending, scheduled, dismissed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_returns');
    }
};
