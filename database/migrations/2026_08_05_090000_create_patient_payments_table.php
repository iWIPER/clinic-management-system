<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_treatment_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('installment_number');
            $table->unsignedSmallInteger('installment_total');
            $table->decimal('amount', 10, 2);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('interest', 10, 2)->default(0);
            $table->string('payment_method', 20)->nullable();
            $table->string('status', 20)->default('pendente'); // pendente, parcial, pago, cancelado (atrasado é calculado em tempo de leitura)
            $table->date('due_date');
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['clinic_id', 'patient_id', 'status']);
            $table->index(['patient_treatment_id', 'installment_number']);
        });

        $this->backfillFinalizedTreatments();
    }

    /**
     * Toda PatientTreatment já finalizada (concluido) precisa de pelo menos
     * uma PatientPayment (1/1, valor integral) para a aba Pagamentos não
     * nascer vazia em clínicas que já têm tratamentos concluídos. Usa
     * DB::table (não Eloquent) por ser um ajuste de dados pontual ligado à
     * criação do schema — mesmo padrão de
     * 2026_06_25_000001_add_inatividade_meses_to_treatments.php.
     */
    private function backfillFinalizedTreatments(): void
    {
        $treatments = DB::table('patient_treatments')
            ->where('status', 'concluido')
            ->get(['id', 'clinic_id', 'patient_id', 'value_charged', 'treatment_date', 'completed_at', 'created_at']);

        foreach ($treatments as $treatment) {
            $transactionId = DB::table('transactions')
                ->where('origem_type', 'App\\Models\\PatientTreatment')
                ->where('origem_id', $treatment->id)
                ->value('id');

            $dueDate = $treatment->completed_at ?? $treatment->treatment_date ?? $treatment->created_at;

            DB::table('patient_payments')->insert([
                'clinic_id'             => $treatment->clinic_id,
                'patient_id'            => $treatment->patient_id,
                'patient_treatment_id'  => $treatment->id,
                'installment_number'    => 1,
                'installment_total'     => 1,
                'amount'                => $treatment->value_charged,
                'amount_paid'           => 0,
                'discount'              => 0,
                'interest'              => 0,
                'status'                => 'pendente',
                'due_date'              => date('Y-m-d', strtotime($dueDate)),
                'transaction_id'        => $transactionId,
                'created_at'            => now(),
                'updated_at'            => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_payments');
    }
};
