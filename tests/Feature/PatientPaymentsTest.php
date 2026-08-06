<?php

use App\Models\Clinic;
use App\Models\Convenio;
use App\Models\Patient;
use App\Models\PatientPayment;
use App\Models\PatientTreatment;
use App\Models\Plan;
use App\Models\Transaction;
use App\Models\Treatment;
use App\Models\User;
use Maatwebsite\Excel\Facades\Excel;

function setupPatientPaymentContext(): array
{
    $plan = Plan::create([
        'name' => 'Test Plan',
        'slug' => 'test-plan-pp-' . uniqid(),
        'is_free' => true,
        'price_monthly_cents' => 0,
        'price_yearly_cents' => 0,
        'max_clinics' => 1,
        'max_patients' => 100,
        'max_users' => 5,
        'storage_gb' => 1,
        'features' => [],
    ]);

    $clinic = Clinic::create([
        'name' => 'Clínica Pagamentos',
        'slug' => 'clinica-pp-' . uniqid(),
        'type' => 'odontologia',
        'status' => 'active',
        'plan_id' => $plan->id,
    ]);

    $user = User::factory()->create(['email_verified_at' => now(), 'job_title' => 'Dentista', 'status' => 'ativo']);
    $clinic->users()->attach($user->id, ['role' => 'owner']);

    session(['current_clinic_id' => $clinic->id]);

    $patient = Patient::create([
        'clinic_id' => $clinic->id,
        'nome' => 'Paciente',
        'sobrenome' => 'Pagamentos',
        'status' => 'ativo',
    ]);

    $convenio = Convenio::create(['clinic_id' => $clinic->id, 'nome' => 'Particular', 'ativo' => true]);

    $catalogTreatment = Treatment::create([
        'clinic_id' => $clinic->id,
        'nome' => 'Consulta Inicial',
        'tipo' => 'procedimento',
        'ativo' => true,
        'duracao_padrao' => 30,
        'preco_base' => 80,
        'custo_padrao' => 80,
    ]);

    $treatment = PatientTreatment::create([
        'clinic_id' => $clinic->id,
        'patient_id' => $patient->id,
        'treatment_id' => $catalogTreatment->id,
        'procedure_name' => 'Consulta Inicial',
        'professional_id' => $user->id,
        'convenio_id' => $convenio->id,
        'budget_code' => PatientTreatment::nextBudgetCode($clinic->id, now()),
        'value_charged' => 600,
        'cost' => 200,
        'status' => PatientTreatment::STATUS_CONCLUIDO,
        'treatment_date' => now()->toDateString(),
        'completed_at' => now(),
        'created_by_id' => $user->id,
    ]);

    return compact('user', 'clinic', 'patient', 'treatment');
}

function makePayment(PatientTreatment $treatment, array $overrides = []): PatientPayment
{
    return PatientPayment::create(array_merge([
        'clinic_id' => $treatment->clinic_id,
        'patient_id' => $treatment->patient_id,
        'patient_treatment_id' => $treatment->id,
        'installment_number' => 1,
        'installment_total' => 1,
        'amount' => 100,
        'amount_paid' => 0,
        'status' => PatientPayment::STATUS_PENDENTE,
        'due_date' => now()->toDateString(),
    ], $overrides));
}

test('patient show page with payments tab exposes payment data', function () {
    ['user' => $user, 'patient' => $patient] = setupPatientPaymentContext();

    $this->actingAs($user)
        ->get(route('patients.show', $patient) . '?tab=payments')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Patients/Show')
            ->where('activeTab', 'payments')
            ->has('patientPayments')
            ->has('paymentSummary')
            ->has('paymentMethods')
            ->has('paymentStatuses')
        );
});

test('payment summary aggregates received, outstanding and overdue correctly, excluding cancelled rows', function () {
    ['user' => $user, 'patient' => $patient, 'treatment' => $treatment] = setupPatientPaymentContext();

    // Recebida integralmente — soma em "received", não entra em outstanding/overdue.
    makePayment($treatment, [
        'installment_number' => 1, 'installment_total' => 4,
        'amount' => 100, 'amount_paid' => 100, 'status' => PatientPayment::STATUS_PAGO,
        'due_date' => now()->subDay()->toDateString(),
    ]);
    // Parcial, ainda não vencida — o saldo (150) entra em "outstanding".
    makePayment($treatment, [
        'installment_number' => 2, 'installment_total' => 4,
        'amount' => 200, 'amount_paid' => 50, 'status' => PatientPayment::STATUS_PARCIAL,
        'due_date' => now()->addDay()->toDateString(),
    ]);
    // Pendente e vencida — entra em "overdue", não em "outstanding".
    makePayment($treatment, [
        'installment_number' => 3, 'installment_total' => 4,
        'amount' => 300, 'amount_paid' => 0, 'status' => PatientPayment::STATUS_PENDENTE,
        'due_date' => now()->subDay()->toDateString(),
    ]);
    // Cancelada — não deve contar em nenhum total, nem em total_charged.
    makePayment($treatment, [
        'installment_number' => 4, 'installment_total' => 4,
        'amount' => 400, 'amount_paid' => 0, 'status' => PatientPayment::STATUS_CANCELADO,
        'due_date' => now()->subDay()->toDateString(),
    ]);

    $this->actingAs($user)
        ->get(route('patients.show', $patient) . '?tab=payments')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Patients/Show')
            ->where('paymentSummary.received', 150)
            ->where('paymentSummary.outstanding', 150)
            ->where('paymentSummary.overdue', 300)
            ->where('paymentSummary.total_charged', 600)
        );
});

test('payments list paginates at 10 per page', function () {
    ['user' => $user, 'patient' => $patient, 'treatment' => $treatment] = setupPatientPaymentContext();

    foreach (range(1, 12) as $i) {
        makePayment($treatment, ['installment_number' => $i, 'installment_total' => 12, 'amount' => 50]);
    }

    $this->actingAs($user)
        ->get(route('patients.show', $patient) . '?tab=payments')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Patients/Show')
            ->where('patientPayments.pagination.total', 12)
            ->where('patientPayments.pagination.last_page', 2)
            ->where('patientPayments.pagination.per_page', 10)
            ->has('patientPayments.data', 10)
        );
});

test('payments_status=atrasado filters to only overdue pendente/parcial installments', function () {
    ['user' => $user, 'patient' => $patient, 'treatment' => $treatment] = setupPatientPaymentContext();

    makePayment($treatment, ['installment_number' => 1, 'installment_total' => 3, 'status' => PatientPayment::STATUS_PENDENTE, 'due_date' => now()->subDay()->toDateString()]);
    makePayment($treatment, ['installment_number' => 2, 'installment_total' => 3, 'status' => PatientPayment::STATUS_PENDENTE, 'due_date' => now()->addDay()->toDateString()]);
    makePayment($treatment, ['installment_number' => 3, 'installment_total' => 3, 'status' => PatientPayment::STATUS_PAGO, 'amount_paid' => 100, 'due_date' => now()->subDay()->toDateString()]);

    $this->actingAs($user)
        ->get(route('patients.show', $patient) . '?tab=payments&payments_status=atrasado')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Patients/Show')
            ->where('patientPayments.pagination.total', 1)
        );
});

// ─── Fase 2: receber, editar, cancelar, excluir ────────────────────────────

test('receiving the full remaining amount marks the installment as pago and syncs the linked transaction', function () {
    ['user' => $user, 'patient' => $patient, 'treatment' => $treatment] = setupPatientPaymentContext();

    $transaction = Transaction::create([
        'clinic_id' => $treatment->clinic_id, 'patient_id' => $patient->id,
        'tipo' => 'receita', 'valor' => 100, 'categoria' => 'Tratamento',
        'origem_type' => PatientTreatment::class, 'origem_id' => $treatment->id, 'status' => 'pendente',
    ]);
    $payment = makePayment($treatment, ['amount' => 100, 'transaction_id' => $transaction->id]);

    $this->actingAs($user)
        ->post(route('patients.payments.receive', [$patient, $payment]), [
            'amount_received' => 100,
            'payment_method' => 'pix',
        ])
        ->assertRedirect();

    $payment->refresh();
    $transaction->refresh();

    expect($payment->status)->toBe(PatientPayment::STATUS_PAGO)
        ->and((float) $payment->amount_paid)->toBe(100.0)
        ->and($payment->paid_at)->not->toBeNull()
        ->and($payment->payment_method)->toBe('pix');

    expect($transaction->status)->toBe('pago')
        ->and($transaction->pago_em)->not->toBeNull();
});

test('receiving a partial amount marks the installment as parcial and leaves the linked transaction pendente', function () {
    ['user' => $user, 'patient' => $patient, 'treatment' => $treatment] = setupPatientPaymentContext();

    $transaction = Transaction::create([
        'clinic_id' => $treatment->clinic_id, 'patient_id' => $patient->id,
        'tipo' => 'receita', 'valor' => 200, 'categoria' => 'Tratamento',
        'origem_type' => PatientTreatment::class, 'origem_id' => $treatment->id, 'status' => 'pendente',
    ]);
    $payment = makePayment($treatment, ['amount' => 200, 'transaction_id' => $transaction->id]);

    $this->actingAs($user)
        ->post(route('patients.payments.receive', [$patient, $payment]), [
            'amount_received' => 80,
            'payment_method' => 'dinheiro',
        ])
        ->assertRedirect();

    $payment->refresh();
    $transaction->refresh();

    expect($payment->status)->toBe(PatientPayment::STATUS_PARCIAL)
        ->and((float) $payment->amount_paid)->toBe(80.0)
        ->and($payment->paid_at)->toBeNull();

    expect($transaction->status)->toBe('pendente');
});

test('a chain of partial receipts eventually settles the installment and only then syncs the transaction', function () {
    ['user' => $user, 'patient' => $patient, 'treatment' => $treatment] = setupPatientPaymentContext();

    $transaction = Transaction::create([
        'clinic_id' => $treatment->clinic_id, 'patient_id' => $patient->id,
        'tipo' => 'receita', 'valor' => 300, 'categoria' => 'Tratamento',
        'origem_type' => PatientTreatment::class, 'origem_id' => $treatment->id, 'status' => 'pendente',
    ]);
    $payment = makePayment($treatment, ['amount' => 300, 'transaction_id' => $transaction->id]);

    // 1º recebimento parcial: 100 de 300.
    $this->actingAs($user)->post(route('patients.payments.receive', [$patient, $payment]), [
        'amount_received' => 100, 'payment_method' => 'dinheiro',
    ])->assertRedirect();
    expect($payment->fresh()->status)->toBe(PatientPayment::STATUS_PARCIAL)
        ->and((float) $payment->fresh()->amount_paid)->toBe(100.0);
    expect($transaction->fresh()->status)->toBe('pendente');

    // 2º recebimento parcial: mais 100 (200 de 300) — ainda parcial.
    $this->actingAs($user)->post(route('patients.payments.receive', [$patient, $payment]), [
        'amount_received' => 100, 'payment_method' => 'pix',
    ])->assertRedirect();
    expect($payment->fresh()->status)->toBe(PatientPayment::STATUS_PARCIAL)
        ->and((float) $payment->fresh()->amount_paid)->toBe(200.0);
    expect($transaction->fresh()->status)->toBe('pendente');

    // 3º recebimento quita o restante (100) — agora sim vira pago e sincroniza a Transaction.
    $this->actingAs($user)->post(route('patients.payments.receive', [$patient, $payment]), [
        'amount_received' => 100, 'payment_method' => 'credito',
    ])->assertRedirect();
    $payment->refresh();
    expect($payment->status)->toBe(PatientPayment::STATUS_PAGO)
        ->and((float) $payment->amount_paid)->toBe(300.0)
        ->and($payment->payment_method)->toBe('credito')
        ->and($payment->paid_at)->not->toBeNull();
    expect($transaction->fresh()->status)->toBe('pago');
});

test('receiving more than the remaining balance is rejected', function () {
    ['user' => $user, 'patient' => $patient, 'treatment' => $treatment] = setupPatientPaymentContext();

    $payment = makePayment($treatment, ['amount' => 100]);

    $this->actingAs($user)
        ->post(route('patients.payments.receive', [$patient, $payment]), [
            'amount_received' => 150,
            'payment_method' => 'pix',
        ])
        ->assertSessionHasErrors('amount_received');

    expect($payment->fresh()->status)->toBe(PatientPayment::STATUS_PENDENTE);
});

test('cannot receive payment on an already paid or cancelled installment', function () {
    ['user' => $user, 'patient' => $patient, 'treatment' => $treatment] = setupPatientPaymentContext();

    $paid = makePayment($treatment, ['amount' => 100, 'amount_paid' => 100, 'status' => PatientPayment::STATUS_PAGO]);
    $cancelled = makePayment($treatment, ['installment_number' => 2, 'amount' => 100, 'status' => PatientPayment::STATUS_CANCELADO]);

    $this->actingAs($user)
        ->post(route('patients.payments.receive', [$patient, $paid]), ['amount_received' => 10, 'payment_method' => 'pix'])
        ->assertSessionHas('error');

    $this->actingAs($user)
        ->post(route('patients.payments.receive', [$patient, $cancelled]), ['amount_received' => 10, 'payment_method' => 'pix'])
        ->assertSessionHas('error');
});

test('discount and interest are honored when computing the remaining balance to receive', function () {
    ['user' => $user, 'patient' => $patient, 'treatment' => $treatment] = setupPatientPaymentContext();

    // Total efetivo = 100 - 20 (desconto) + 10 (juros) = 90.
    $payment = makePayment($treatment, ['amount' => 100, 'discount' => 20, 'interest' => 10]);

    expect($payment->remaining())->toBe(90.0);

    $this->actingAs($user)
        ->post(route('patients.payments.receive', [$patient, $payment]), [
            'amount_received' => 90,
            'payment_method' => 'pix',
        ])
        ->assertRedirect();

    expect($payment->fresh()->status)->toBe(PatientPayment::STATUS_PAGO);
});

test('editing a payment is only allowed before anything has been received', function () {
    ['user' => $user, 'patient' => $patient, 'treatment' => $treatment] = setupPatientPaymentContext();

    $untouched = makePayment($treatment, ['amount' => 100]);
    $touched = makePayment($treatment, ['installment_number' => 2, 'amount' => 100, 'amount_paid' => 30, 'status' => PatientPayment::STATUS_PARCIAL]);

    $this->actingAs($user)
        ->put(route('patients.payments.update', [$patient, $untouched]), [
            'due_date' => now()->addDays(10)->toDateString(),
            'discount' => 15,
            'interest' => 0,
        ])
        ->assertRedirect();

    expect((float) $untouched->fresh()->discount)->toBe(15.0);

    $this->actingAs($user)
        ->put(route('patients.payments.update', [$patient, $touched]), [
            'due_date' => now()->addDays(10)->toDateString(),
            'discount' => 15,
            'interest' => 0,
        ])
        ->assertSessionHas('error');

    expect((float) $touched->fresh()->discount)->toBe(0.0);
});

test('cancelling a payment is only allowed while nothing has been received, and cancels the linked transaction', function () {
    ['user' => $user, 'patient' => $patient, 'treatment' => $treatment] = setupPatientPaymentContext();

    $transaction = Transaction::create([
        'clinic_id' => $treatment->clinic_id, 'patient_id' => $patient->id,
        'tipo' => 'receita', 'valor' => 100, 'categoria' => 'Tratamento',
        'origem_type' => PatientTreatment::class, 'origem_id' => $treatment->id, 'status' => 'pendente',
    ]);
    $payment = makePayment($treatment, ['amount' => 100, 'transaction_id' => $transaction->id]);

    $this->actingAs($user)
        ->post(route('patients.payments.cancel', [$patient, $payment]))
        ->assertRedirect();

    expect($payment->fresh()->status)->toBe(PatientPayment::STATUS_CANCELADO)
        ->and($transaction->fresh()->status)->toBe('cancelado');

    $touched = makePayment($treatment, ['installment_number' => 2, 'amount' => 100, 'amount_paid' => 20, 'status' => PatientPayment::STATUS_PARCIAL]);
    $this->actingAs($user)
        ->post(route('patients.payments.cancel', [$patient, $touched]))
        ->assertSessionHas('error');

    expect($touched->fresh()->status)->toBe(PatientPayment::STATUS_PARCIAL);
});

test('deleting a payment is only allowed while pending and untouched, and removes the linked transaction', function () {
    ['user' => $user, 'patient' => $patient, 'treatment' => $treatment] = setupPatientPaymentContext();

    $transaction = Transaction::create([
        'clinic_id' => $treatment->clinic_id, 'patient_id' => $patient->id,
        'tipo' => 'receita', 'valor' => 100, 'categoria' => 'Tratamento',
        'origem_type' => PatientTreatment::class, 'origem_id' => $treatment->id, 'status' => 'pendente',
    ]);
    $payment = makePayment($treatment, ['amount' => 100, 'transaction_id' => $transaction->id]);

    $this->actingAs($user)
        ->delete(route('patients.payments.destroy', [$patient, $payment]))
        ->assertRedirect();

    expect(PatientPayment::find($payment->id))->toBeNull()
        ->and(Transaction::find($transaction->id))->toBeNull();
});

test('finalizing a treatment auto-creates a linked 1/1 payment when none exists yet', function () {
    ['user' => $user, 'patient' => $patient] = setupPatientPaymentContext();

    $catalogTreatment = Treatment::create([
        'clinic_id' => $patient->clinic_id, 'nome' => 'Limpeza', 'tipo' => 'procedimento',
        'ativo' => true, 'duracao_padrao' => 30, 'preco_base' => 150, 'custo_padrao' => 50,
    ]);
    $pt = PatientTreatment::create([
        'clinic_id' => $patient->clinic_id, 'patient_id' => $patient->id,
        'treatment_id' => $catalogTreatment->id, 'procedure_name' => $catalogTreatment->nome,
        'budget_code' => PatientTreatment::nextBudgetCode($patient->clinic_id, now()),
        'value_charged' => 150, 'cost' => 50, 'status' => 'futuro', 'treatment_date' => now()->toDateString(),
    ]);

    $this->actingAs($user)
        ->post(route('patients.treatments.finalize', [$patient, $pt]), [
            'professional_id' => $user->id,
            'completed_at' => now()->toDateString(),
            'update_stock' => false,
        ])
        ->assertRedirect();

    $payment = PatientPayment::where('patient_treatment_id', $pt->id)->first();
    $transaction = Transaction::where('origem_type', PatientTreatment::class)->where('origem_id', $pt->id)->first();

    expect($payment)->not->toBeNull()
        ->and($payment->installment_number)->toBe(1)
        ->and($payment->installment_total)->toBe(1)
        ->and((float) $payment->amount)->toBe(150.0)
        ->and($payment->status)->toBe(PatientPayment::STATUS_PENDENTE)
        ->and($payment->transaction_id)->toBe($transaction->id);

    // Finalizar de novo (ex.: reprocessamento acidental) não duplica a parcela.
    expect(PatientPayment::where('patient_treatment_id', $pt->id)->count())->toBe(1);
});

test('finance totalReceita reflects a fully received treatment payment, closing the gap it never covered before', function () {
    ['user' => $user, 'patient' => $patient, 'treatment' => $treatment] = setupPatientPaymentContext();

    $transaction = Transaction::create([
        'clinic_id' => $treatment->clinic_id, 'patient_id' => $patient->id,
        'tipo' => 'receita', 'valor' => 300, 'categoria' => 'Tratamento',
        'origem_type' => PatientTreatment::class, 'origem_id' => $treatment->id, 'status' => 'pendente',
    ]);
    $payment = makePayment($treatment, ['amount' => 300, 'transaction_id' => $transaction->id]);

    $this->actingAs($user)
        ->post(route('patients.payments.receive', [$patient, $payment]), [
            'amount_received' => 300,
            'payment_method' => 'credito',
        ])
        ->assertRedirect();

    $this->actingAs($user)
        ->get(route('finance.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('totalReceita', 300));
});

// ─── Fase 3: plano de parcelas ──────────────────────────────────────────────

test('creating a payment plan replaces the existing installment with N evenly-divided ones and keeps the transaction total correct', function () {
    ['user' => $user, 'patient' => $patient, 'treatment' => $treatment] = setupPatientPaymentContext();
    // $treatment->value_charged é 600 (ver setupPatientPaymentContext).

    $oldTransaction = Transaction::create([
        'clinic_id' => $treatment->clinic_id, 'patient_id' => $patient->id,
        'tipo' => 'receita', 'valor' => 600, 'categoria' => 'Tratamento',
        'origem_type' => PatientTreatment::class, 'origem_id' => $treatment->id, 'status' => 'pendente',
    ]);
    $oldPayment = makePayment($treatment, ['amount' => 600, 'transaction_id' => $oldTransaction->id]);

    $this->actingAs($user)
        ->post(route('patients.treatments.payment-plan', [$patient, $treatment]), [
            'installments' => 4,
            'first_due_date' => '2026-09-01',
            'interval_days' => 30,
        ])
        ->assertRedirect();

    expect(PatientPayment::find($oldPayment->id))->toBeNull()
        ->and(Transaction::find($oldTransaction->id))->toBeNull();

    $payments = PatientPayment::where('patient_treatment_id', $treatment->id)->orderBy('installment_number')->get();

    expect($payments)->toHaveCount(4)
        ->and((float) $payments->sum('amount'))->toBe(600.0);

    foreach ($payments as $i => $payment) {
        expect($payment->installment_number)->toBe($i + 1)
            ->and($payment->installment_total)->toBe(4)
            ->and((float) $payment->amount)->toBe(150.0)
            ->and($payment->due_date->toDateString())->toBe(now()->parse('2026-09-01')->addDays(30 * $i)->toDateString())
            ->and($payment->transaction_id)->not->toBeNull();

        $transaction = Transaction::find($payment->transaction_id);
        expect($transaction)->not->toBeNull()
            ->and((float) $transaction->valor)->toBe(150.0)
            ->and($transaction->origem_type)->toBe(PatientPayment::class)
            ->and($transaction->origem_id)->toBe($payment->id);
    }
});

test('payment plan remainder cents are absorbed by the last installment so the sum matches the treatment value exactly', function () {
    ['user' => $user, 'patient' => $patient, 'clinic' => $clinic] = setupPatientPaymentContext();

    $catalogTreatment = Treatment::create([
        'clinic_id' => $clinic->id, 'nome' => 'Canal', 'tipo' => 'procedimento',
        'ativo' => true, 'duracao_padrao' => 60, 'preco_base' => 100, 'custo_padrao' => 40,
    ]);
    $treatment = PatientTreatment::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'treatment_id' => $catalogTreatment->id,
        'procedure_name' => 'Canal',
        'budget_code' => PatientTreatment::nextBudgetCode($clinic->id, now()),
        'value_charged' => 100, 'cost' => 40, 'status' => PatientTreatment::STATUS_CONCLUIDO,
        'treatment_date' => now()->toDateString(), 'completed_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('patients.treatments.payment-plan', [$patient, $treatment]), [
            'installments' => 3,
            'first_due_date' => now()->toDateString(),
            'interval_days' => 30,
        ])
        ->assertRedirect();

    $amounts = PatientPayment::where('patient_treatment_id', $treatment->id)
        ->orderBy('installment_number')->pluck('amount')->map(fn ($a) => (float) $a)->all();

    expect($amounts)->toBe([33.33, 33.33, 33.34])
        ->and(array_sum($amounts))->toBe(100.0);
});

test('creating a payment plan is rejected once any installment of the treatment has received a payment', function () {
    ['user' => $user, 'patient' => $patient, 'treatment' => $treatment] = setupPatientPaymentContext();

    $touched = makePayment($treatment, ['amount' => 600, 'amount_paid' => 100, 'status' => PatientPayment::STATUS_PARCIAL]);

    $this->actingAs($user)
        ->post(route('patients.treatments.payment-plan', [$patient, $treatment]), [
            'installments' => 3,
            'first_due_date' => now()->toDateString(),
            'interval_days' => 30,
        ])
        ->assertSessionHas('error');

    expect(PatientPayment::where('patient_treatment_id', $treatment->id)->count())->toBe(1)
        ->and(PatientPayment::find($touched->id))->not->toBeNull();
});

test('payment plan installment count is capped at PatientPayment::MAX_INSTALLMENTS', function () {
    ['user' => $user, 'patient' => $patient, 'treatment' => $treatment] = setupPatientPaymentContext();

    $this->actingAs($user)
        ->post(route('patients.treatments.payment-plan', [$patient, $treatment]), [
            'installments' => PatientPayment::MAX_INSTALLMENTS + 1,
            'first_due_date' => now()->toDateString(),
            'interval_days' => 30,
        ])
        ->assertSessionHasErrors('installments');
});

// ─── Fase 4: comprovante e exportação ───────────────────────────────────────

test('receipt is not accessible for a payment that has not received anything yet', function () {
    ['user' => $user, 'patient' => $patient, 'treatment' => $treatment] = setupPatientPaymentContext();

    $payment = makePayment($treatment, ['amount' => 100]);

    $this->actingAs($user)
        ->get(route('patients.payments.receipt', [$patient, $payment]))
        ->assertNotFound();
});

test('receipt is accessible once a payment has been fully or partially received', function () {
    ['user' => $user, 'patient' => $patient, 'treatment' => $treatment] = setupPatientPaymentContext();

    $paid = makePayment($treatment, ['amount' => 100, 'amount_paid' => 100, 'status' => PatientPayment::STATUS_PAGO, 'payment_method' => 'pix', 'paid_at' => now()]);
    $partial = makePayment($treatment, ['installment_number' => 2, 'amount' => 100, 'amount_paid' => 40, 'status' => PatientPayment::STATUS_PARCIAL, 'payment_method' => 'dinheiro']);

    $this->actingAs($user)
        ->get(route('patients.payments.receipt', [$patient, $paid]))
        ->assertOk()
        ->assertSee('Comprovante de Pagamento')
        ->assertSee('PIX');

    $this->actingAs($user)
        ->get(route('patients.payments.receipt', [$patient, $partial]))
        ->assertOk()
        ->assertSee('Saldo devedor');
});

test('csv export of payments includes the expected columns and respects the status filter', function () {
    ['user' => $user, 'patient' => $patient, 'treatment' => $treatment] = setupPatientPaymentContext();

    makePayment($treatment, ['installment_number' => 1, 'installment_total' => 2, 'amount' => 100, 'amount_paid' => 100, 'status' => PatientPayment::STATUS_PAGO, 'payment_method' => 'pix']);
    makePayment($treatment, ['installment_number' => 2, 'installment_total' => 2, 'amount' => 100, 'status' => PatientPayment::STATUS_PENDENTE]);

    $response = $this->actingAs($user)
        ->get(route('patients.payments.export', [$patient, 'format' => 'csv']));

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $content = $response->streamedContent();

    expect($content)->toContain('Tratamento')->toContain('Parcela')->toContain('Forma de pagamento');

    $filtered = $this->actingAs($user)
        ->get(route('patients.payments.export', [$patient, 'format' => 'csv', 'payments_status' => 'pago']))
        ->streamedContent();

    expect($filtered)->toContain('1/2')->not->toContain('2/2');
});

test('excel export of payments downloads a file with the expected headings', function () {
    ['user' => $user, 'patient' => $patient, 'treatment' => $treatment] = setupPatientPaymentContext();

    makePayment($treatment, ['amount' => 100]);

    Excel::fake();

    $this->actingAs($user)
        ->get(route('patients.payments.export', [$patient, 'format' => 'excel']))
        ->assertOk();

    Excel::assertDownloaded('pagamentos-' . $patient->id . '-' . now()->format('Y-m-d') . '.xlsx', function ($export) {
        return $export->headings()[0] === 'Tratamento' && in_array('Status', $export->headings(), true);
    });
});

test('an invalid payments export format is rejected', function () {
    ['user' => $user, 'patient' => $patient] = setupPatientPaymentContext();

    $this->actingAs($user)
        ->get(route('patients.payments.export', [$patient, 'format' => 'pdf']))
        ->assertStatus(422);
});

// ─── Fase 5: filtro de período ──────────────────────────────────────────────

test('payments_period=este_mes only includes installments due within the current month', function () {
    ['user' => $user, 'patient' => $patient, 'treatment' => $treatment] = setupPatientPaymentContext();

    makePayment($treatment, ['installment_number' => 1, 'installment_total' => 2, 'due_date' => now()->startOfMonth()->addDays(2)->toDateString()]);
    makePayment($treatment, ['installment_number' => 2, 'installment_total' => 2, 'due_date' => now()->addMonths(2)->toDateString()]);

    $this->actingAs($user)
        ->get(route('patients.show', $patient) . '?tab=payments&payments_period=este_mes')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Patients/Show')
            ->where('patientPayments.pagination.total', 1)
        );
});
