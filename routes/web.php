<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Convites de cadastro — wizard público do paciente (Fase 2, ver docs/PATIENT_INVITATIONS_BRD.md §8)
Route::get('/p/{token}', [\App\Http\Controllers\Public\PatientInvitePublicController::class, 'show'])->name('patient-invites.public.show');
Route::patch('/p/{token}', [\App\Http\Controllers\Public\PatientInvitePublicController::class, 'update'])
    ->middleware('throttle:60,1')
    ->name('patient-invites.public.update');
Route::post('/p/{token}/concluir', [\App\Http\Controllers\Public\PatientInvitePublicController::class, 'complete'])
    ->middleware('throttle:20,1')
    ->name('patient-invites.public.complete');

// Rotas públicas
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
});

// Rotas autenticadas
Route::middleware(['auth', 'verified'])->group(function () {

    // Onboarding (deve vir antes do middleware de clinic em alguns casos)
    Route::prefix('onboarding')->name('onboarding.')->group(function () {
        Route::get('/choose-role', [\App\Http\Controllers\OnboardingController::class, 'showRoleChoice'])->name('choose-role');
        Route::post('/choose-role', [\App\Http\Controllers\OnboardingController::class, 'chooseRole']);

        Route::get('/create-clinic', [\App\Http\Controllers\OnboardingController::class, 'createClinic'])->name('create-clinic');
        Route::post('/create-clinic', [\App\Http\Controllers\OnboardingController::class, 'storeClinic']);

        Route::get('/invite-team', [\App\Http\Controllers\OnboardingController::class, 'inviteTeam'])->name('invite-team');
        Route::post('/invite-team', [\App\Http\Controllers\OnboardingController::class, 'sendInvites']);

        Route::get('/join', [\App\Http\Controllers\OnboardingController::class, 'joinInvite'])->name('join-invite');
        Route::post('/join', [\App\Http\Controllers\OnboardingController::class, 'acceptInvite']);
    });

    // A partir daqui exigimos uma clínica ativa
    Route::middleware('clinic')->group(function () {
        Route::get('/dashboard', function () {
            $user = auth()->user();
            $clinicId = session('current_clinic_id');

            if (!$clinicId) {
                $clinic = $user?->clinics()->first();
                if ($clinic) {
                    session(['current_clinic_id' => $clinic->id]);
                    session(['current_clinic' => $clinic->only('id', 'name', 'type')]);
                } else {
                    return redirect()->route('onboarding.choose-role');
                }
            }

            return Inertia::render('Dashboard', [
                'clinic' => session('current_clinic'),
                'stats' => [
                    'patients' => 12,
                    'appointments_today' => 4,
                    'consultations_in_progress' => 1,
                    'revenue_month' => '4.850,00',
                ],
            ]);
        })->name('dashboard');

        // Pacientes - CRUD completo
        Route::get('/patients', [\App\Http\Controllers\PatientController::class, 'index'])->name('patients.index');
        Route::get('/patients/create', [\App\Http\Controllers\PatientController::class, 'create'])->name('patients.create');
        Route::post('/patients', [\App\Http\Controllers\PatientController::class, 'store'])->name('patients.store');
        Route::get('/patients/{patient}', [\App\Http\Controllers\PatientController::class, 'show'])->name('patients.show');
        Route::get('/patients/{patient}/edit', [\App\Http\Controllers\PatientController::class, 'edit'])->name('patients.edit');
        Route::put('/patients/{patient}', [\App\Http\Controllers\PatientController::class, 'update'])->name('patients.update');
        Route::delete('/patients/{patient}', [\App\Http\Controllers\PatientController::class, 'destroy'])->name('patients.destroy');

        // Prontuário odontológico
        Route::get('/patients/{patient}/prontuario', [\App\Http\Controllers\PatientProntuarioController::class, 'show'])->name('patients.prontuario');
        Route::put('/patients/{patient}/prontuario/anamnesis', [\App\Http\Controllers\PatientProntuarioController::class, 'updateAnamnesis'])->name('patients.prontuario.anamnesis');
        Route::post('/patients/{patient}/prontuario/evolutions', [\App\Http\Controllers\PatientProntuarioController::class, 'storeEvolution'])->name('patients.prontuario.evolutions');
        Route::put('/patients/{patient}/prontuario/odontogram', [\App\Http\Controllers\PatientProntuarioController::class, 'updateOdontogram'])->name('patients.prontuario.odontogram');
        Route::get('/patients/{patient}/prontuario/pdf', [\App\Http\Controllers\PatientProntuarioController::class, 'generatePdf'])->name('patients.prontuario.pdf');

        // Convites de cadastro — lado da recepção (Fase 1, ver docs/PATIENT_INVITATIONS_BRD.md §7)
        Route::prefix('patient-invites')->name('patient-invites.')->group(function () {
            Route::get('/check-phone', [\App\Http\Controllers\PatientInviteController::class, 'checkPhone'])->name('check-phone');
            Route::post('/', [\App\Http\Controllers\PatientInviteController::class, 'store'])->name('store');
            Route::get('/{invite}/qrcode', [\App\Http\Controllers\PatientInviteController::class, 'qrcode'])->name('qrcode');
            Route::post('/{invite}/resend', [\App\Http\Controllers\PatientInviteController::class, 'resend'])->name('resend');
            Route::post('/{invite}/cancel', [\App\Http\Controllers\PatientInviteController::class, 'cancel'])->name('cancel');
            Route::post('/{invite}/regenerate', [\App\Http\Controllers\PatientInviteController::class, 'regenerate'])->name('regenerate');
            Route::post('/{invite}/log-event', [\App\Http\Controllers\PatientInviteController::class, 'logEvent'])->name('log-event');
        });

        // Agenda (Agendamentos)
        Route::get('/appointments', [\App\Http\Controllers\AppointmentController::class, 'index'])->name('appointments.index');
        Route::get('/appointments/create', [\App\Http\Controllers\AppointmentController::class, 'create'])->name('appointments.create');
        Route::get('/appointments/fullscreen', [\App\Http\Controllers\AppointmentController::class, 'fullscreen'])->name('appointments.fullscreen');
        Route::post('/appointments', [\App\Http\Controllers\AppointmentController::class, 'store'])->name('appointments.store');
        Route::get('/appointments/{appointment}/edit', [\App\Http\Controllers\AppointmentController::class, 'edit'])->name('appointments.edit');
        Route::put('/appointments/{appointment}', [\App\Http\Controllers\AppointmentController::class, 'update'])->name('appointments.update');
        Route::post('/appointments/{appointment}/check-in', [\App\Http\Controllers\AppointmentController::class, 'checkIn'])->name('appointments.check-in');
        Route::patch('/appointments/{appointment}/status', [\App\Http\Controllers\AppointmentController::class, 'updateStatus'])->name('appointments.update-status');
        Route::delete('/appointments/{appointment}', [\App\Http\Controllers\AppointmentController::class, 'destroy'])->name('appointments.destroy');

        // Histórico de atendimentos (registros permanentes)
        Route::get('/clinical-records', [\App\Http\Controllers\ClinicalRecordController::class, 'index'])->name('clinical-records.index');
        Route::get('/clinical-records/{clinicalRecord}', [\App\Http\Controllers\ClinicalRecordController::class, 'show'])->name('clinical-records.show');
        Route::get('/clinical-records/{clinicalRecord}/pdf', [\App\Http\Controllers\ClinicalRecordController::class, 'generatePdf'])->name('clinical-records.pdf');

        // Configurações da clínica
        Route::get('/clinic-settings', [\App\Http\Controllers\ClinicSettingsController::class, 'edit'])->name('clinic-settings.edit');
        Route::post('/clinic-settings', [\App\Http\Controllers\ClinicSettingsController::class, 'update'])->name('clinic-settings.update');

        // Consultas (fluxo de atendimento)
        Route::get('/consultations', [\App\Http\Controllers\ConsultationController::class, 'index'])->name('consultations.index');
        Route::get('/consultations/{consultation}', [\App\Http\Controllers\ConsultationController::class, 'show'])->name('consultations.show');
        Route::post('/consultations/{appointment}/check-in', [\App\Http\Controllers\ConsultationController::class, 'checkIn'])->name('consultations.check-in');
        Route::post('/consultations/{consultation}/start', [\App\Http\Controllers\ConsultationController::class, 'start'])->name('consultations.start');
        Route::post('/consultations/{consultation}/finish', [\App\Http\Controllers\ConsultationController::class, 'finish'])->name('consultations.finish');
        Route::put('/consultations/{consultation}', [\App\Http\Controllers\ConsultationController::class, 'update'])->name('consultations.update');
        Route::post('/consultations/{consultation}/add-execution', [\App\Http\Controllers\ConsultationController::class, 'addExecution'])->name('consultations.add-execution');
        // Procedimentos / Catálogo de Tratamentos
        Route::get('/treatments', [\App\Http\Controllers\TreatmentController::class, 'index'])->name('treatments.index');
        Route::get('/treatments/create', [\App\Http\Controllers\TreatmentController::class, 'create'])->name('treatments.create');
        Route::post('/treatments', [\App\Http\Controllers\TreatmentController::class, 'store'])->name('treatments.store');
        Route::get('/treatments/{treatment}', [\App\Http\Controllers\TreatmentController::class, 'show'])->name('treatments.show');
        Route::get('/treatments/{treatment}/edit', [\App\Http\Controllers\TreatmentController::class, 'edit'])->name('treatments.edit');
        Route::put('/treatments/{treatment}', [\App\Http\Controllers\TreatmentController::class, 'update'])->name('treatments.update');
        Route::post('/treatments/{treatment}/deactivate', [\App\Http\Controllers\TreatmentController::class, 'deactivate'])->name('treatments.deactivate');
        Route::post('/treatments/{treatment}/reactivate', [\App\Http\Controllers\TreatmentController::class, 'reactivate'])->name('treatments.reactivate');
        Route::delete('/treatments/{treatment}', [\App\Http\Controllers\TreatmentController::class, 'destroy'])->name('treatments.destroy');
        // Estoque básico
        Route::get('/inventory', [\App\Http\Controllers\InventoryController::class, 'index'])->name('inventory.index');
        Route::get('/inventory/create', [\App\Http\Controllers\InventoryController::class, 'create'])->name('inventory.create');
        Route::post('/inventory', [\App\Http\Controllers\InventoryController::class, 'store'])->name('inventory.store');
        Route::post('/inventory/{item}/add-stock', [\App\Http\Controllers\InventoryController::class, 'addStock'])->name('inventory.add-stock');
        // Financeiro básico
        Route::get('/finance', [\App\Http\Controllers\FinanceController::class, 'index'])->name('finance.index');
        Route::post('/finance/transactions', [\App\Http\Controllers\FinanceController::class, 'storeTransaction'])->name('finance.store-transaction');
        Route::post('/finance/pricing', [\App\Http\Controllers\FinanceController::class, 'updatePricing'])->name('finance.update-pricing');
        Route::post('/finance/budgets', [\App\Http\Controllers\FinanceController::class, 'createBudgetFromExecution'])->name('finance.create-budget');

        // Notificações (polling)
        Route::get('/notifications/counts', function () {
            $now = \Illuminate\Support\Carbon::now();

            $aguardandoConfirmacao = \App\Models\Appointment::whereIn('status', ['scheduled'])
                ->whereDate('start', today())
                ->count();

            $aguardandoAtendimento = \App\Models\Consultation::where('status', 'aguardando')->count();

            $esperando15min = \App\Models\Consultation::where('status', 'aguardando')
                ->where('check_in_at', '<=', $now->copy()->subMinutes(15))
                ->count();

            $consultaProxima = \App\Models\Appointment::whereIn('status', ['scheduled', 'confirmed'])
                ->whereBetween('start', [$now, $now->copy()->addMinutes(30)])
                ->count();

            return response()->json([
                'total'                  => $aguardandoConfirmacao + $aguardandoAtendimento + $esperando15min + $consultaProxima,
                'aguardando_confirmacao' => $aguardandoConfirmacao,
                'aguardando_atendimento' => $aguardandoAtendimento,
                'esperando_15min'        => $esperando15min,
                'consulta_proxima'       => $consultaProxima,
            ]);
        })->name('notifications.counts');

        // Profile (Breeze)
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

        // Fotos clínicas
        Route::post('/patients/{patient}/photos', [App\Http\Controllers\GoogleDriveController::class, 'uploadPhoto'])->name('patients.photos.upload');
        Route::get('/patients/{patient}/photos/{photo}', [App\Http\Controllers\GoogleDriveController::class, 'viewPhoto'])->name('patients.photos.view');
        Route::post('/drive/confirm-disclaimer', [App\Http\Controllers\GoogleDriveController::class, 'confirmDisclaimer'])->name('drive.confirm-disclaimer');
        Route::post('/patients/{patient}/drive/verify', [App\Http\Controllers\GoogleDriveController::class, 'verifyIntegrity'])->name('patients.drive.verify');
    });
});

// Google Drive OAuth — fora do auth para suportar o redirect loop do Google,
// mas callback valida Auth::user() internamente.
Route::middleware('auth')->group(function () {
    Route::get('/auth/google', [App\Http\Controllers\GoogleDriveController::class, 'connect'])->name('google.connect');
    Route::get('/auth/google/callback', [App\Http\Controllers\GoogleDriveController::class, 'callback'])->name('google.callback');
    Route::post('/auth/google/disconnect/{clinic}', [App\Http\Controllers\GoogleDriveController::class, 'disconnect'])->name('google.disconnect');
});
