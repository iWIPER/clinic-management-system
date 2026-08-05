<?php

use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Regra de domínio: BRD PATIENT_INVITATIONS_BRD.md §5.2 — no máximo um
// convite não-terminal por (patient_id, kind), garantido em nível de banco
// pelo índice único sobre a coluna gerada active_key. Este teste roda sob
// SQLite (phpunit.xml) e prova que a garantia é idêntica à já verificada
// manualmente em MySQL — mesma regra, drivers diferentes.
function setupPatientInviteContext(): array
{
    $plan = Plan::create([
        'name' => 'Test Plan',
        'slug' => 'test-plan-invite-' . uniqid(),
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
        'name' => 'Clínica Teste',
        'slug' => 'clinica-invite-' . uniqid(),
        'type' => 'odontologia',
        'status' => 'active',
        'plan_id' => $plan->id,
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $clinic->users()->attach($user->id, ['role' => 'owner']);

    $patient = Patient::create([
        'clinic_id' => $clinic->id,
        'nome' => 'Paciente',
        'sobrenome' => 'Convite',
        'status' => 'ativo',
    ]);

    session(['current_clinic_id' => $clinic->id]);

    return compact('clinic', 'user', 'patient');
}

function insertPatientInvite(int $clinicId, int $patientId, int $userId, string $kind, string $status): void
{
    DB::table('patient_invites')->insert([
        'clinic_id'  => $clinicId,
        'patient_id' => $patientId,
        'kind'       => $kind,
        'token'      => Str::random(40),
        'status'     => $status,
        'channel'    => 'link_only',
        'created_by' => $userId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('rejects a second active invite for the same patient and kind', function () {
    ['clinic' => $clinic, 'user' => $user, 'patient' => $patient] = setupPatientInviteContext();

    insertPatientInvite($clinic->id, $patient->id, $user->id, 'cadastro', 'gerado');

    expect(fn () => insertPatientInvite($clinic->id, $patient->id, $user->id, 'cadastro', 'visualizado'))
        ->toThrow(QueryException::class);

    expect(DB::table('patient_invites')->where('patient_id', $patient->id)->count())->toBe(1);
});

test('allows unlimited terminal invites for the same patient and kind', function () {
    ['clinic' => $clinic, 'user' => $user, 'patient' => $patient] = setupPatientInviteContext();

    foreach (['expirado', 'cancelado', 'concluido', 'expirado', 'cancelado'] as $status) {
        insertPatientInvite($clinic->id, $patient->id, $user->id, 'cadastro', $status);
    }

    expect(DB::table('patient_invites')->where('patient_id', $patient->id)->count())->toBe(5);
});

test('allows one active invite per kind simultaneously for the same patient', function () {
    ['clinic' => $clinic, 'user' => $user, 'patient' => $patient] = setupPatientInviteContext();

    insertPatientInvite($clinic->id, $patient->id, $user->id, 'cadastro', 'gerado');
    insertPatientInvite($clinic->id, $patient->id, $user->id, 'atualizacao', 'gerado');

    expect(DB::table('patient_invites')->where('patient_id', $patient->id)->count())->toBe(2);
});
