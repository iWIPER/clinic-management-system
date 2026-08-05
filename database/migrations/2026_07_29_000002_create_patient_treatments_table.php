<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_treatments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('treatment_id')->nullable()->constrained('treatments')->nullOnDelete();
            $table->string('procedure_name');
            $table->foreignId('professional_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('convenio_id')->nullable()->constrained('convenios')->nullOnDelete();
            $table->string('budget_code');
            $table->string('tooth', 10)->nullable();
            $table->json('faces')->nullable();
            $table->decimal('value_charged', 10, 2)->default(0);
            $table->decimal('cost', 10, 2)->default(0);
            $table->string('status', 20)->default('futuro'); // futuro, em_andamento, concluido
            $table->date('treatment_date')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('stock_updated_at')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['clinic_id', 'patient_id', 'status']);
            $table->index(['patient_id', 'tooth']);
            $table->unique(['clinic_id', 'budget_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_treatments');
    }
};
