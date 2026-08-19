<?php

use App\Models\User;
use App\Services\GoogleDriveHealthCheckService;

test('health check returns structured json report', function () {
    ['user' => $user, 'patient' => $patient] = setupDriveUploadContext();

    $report = [
        'checked_at'     => now()->toIso8601String(),
        'checked_by'     => ['id' => $user->id, 'name' => $user->name],
        'patient_name'   => 'João Silva',
        'health_score'   => 98,
        'connection'     => ['status' => 'ok', 'connected' => true, 'email' => 'clinic@example.com', 'message' => 'Conta conectada corretamente.'],
        'storage'        => ['status' => 'ok', 'percentage' => 39, 'level' => 'ok', 'message' => 'Tudo dentro do esperado.'],
        'folders'        => ['status' => 'ok', 'items' => [], 'has_issues' => false, 'can_repair' => false],
        'files'          => ['status' => 'ok', 'db_count' => 8, 'drive_count' => 8, 'missing_count' => 0, 'missing' => []],
        'orphans'        => ['status' => 'ok', 'orphan_count' => 0, 'items' => []],
        'permissions'    => ['status' => 'ok', 'items' => []],
        'api'            => ['status' => 'ok', 'items' => [], 'reconnect_required' => false],
        'recommendations'=> ['Tudo funcionando corretamente.'],
        'audit_summary'  => [['icon' => '✓', 'description' => 'Verificação iniciada']],
    ];

    $mock = Mockery::mock(GoogleDriveHealthCheckService::class);
    $mock->shouldReceive('run')->once()->andReturn($report);
    $this->app->instance(GoogleDriveHealthCheckService::class, $mock);

    $this->actingAs($user)
        ->postJson(route('patients.drive.health-check', $patient))
        ->assertOk()
        ->assertJsonPath('health_score', 98)
        ->assertJsonPath('connection.connected', true)
        ->assertJsonPath('files.db_count', 8)
        ->assertJsonPath('recommendations.0', 'Tudo funcionando corretamente.');
});

test('health check never returns generic error on unexpected failure', function () {
    ['user' => $user, 'patient' => $patient] = setupDriveUploadContext();

    $mock = Mockery::mock(GoogleDriveHealthCheckService::class);
    $mock->shouldReceive('run')->once()->andThrow(new RuntimeException('API timeout'));
    $mock->shouldReceive('buildFailureReport')
        ->once()
        ->andReturn([
            'health_score'    => 50,
            'partial_failure' => true,
            'connection'      => ['connected' => true, 'message' => 'Conta conectada, mas parte da verificação não pôde ser concluída.'],
            'recommendations' => ['Algumas etapas da verificação falharam. Revise as seções marcadas e tente novamente em instantes.'],
            'audit_summary'   => [],
        ]);

    $this->app->instance(GoogleDriveHealthCheckService::class, $mock);

    $this->actingAs($user)
        ->postJson(route('patients.drive.health-check', $patient))
        ->assertOk()
        ->assertJsonPath('partial_failure', true)
        ->assertJsonPath('health_score', 50)
        ->assertJsonMissing(['error' => 'Não foi possível verificar o Google Drive']);
});

test('health check is forbidden for users outside patient clinic', function () {
    ['patient' => $patient] = setupDriveUploadContext();

    // Precisa de uma clínica PRÓPRIA (diferente da do paciente), não zero
    // clínicas — um usuário sem nenhuma clínica é barrado antes disso pelo
    // EnsureCurrentClinic (ver ClinicIsolationTest); aqui o alvo é o
    // abort_unless(clinics()->where('clinics.id', $patient->clinic_id)...)
    // dentro do próprio controller.
    ['user' => $outsider] = setupDriveUploadContext();

    $this->actingAs($outsider)
        ->postJson(route('patients.drive.health-check', $patient))
        ->assertForbidden();
});