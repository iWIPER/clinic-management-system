<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Links de indicação (públicos)
Route::get('/r/{code}', [\App\Http\Controllers\ReferralController::class, 'redirect'])->name('referral.redirect');
Route::get('/ref/{code}', [\App\Http\Controllers\ReferralController::class, 'redirect']);

// Validação de documento de anamnese (público, sem login)
Route::get('/anamneses/validar/{token}', [\App\Http\Controllers\AnamnesisValidationController::class, 'show'])->name('anamneses.validate');

// Documentos — validação pública (somente leitura) e assinatura remota por token
Route::get('/documentos/validar/{token}', [\App\Http\Controllers\DocumentValidationController::class, 'show'])->name('documents.validate');
Route::get('/documentos/assinar/{token}', [\App\Http\Controllers\Public\DocumentPublicSignatureController::class, 'show'])->name('documents.public-sign');
Route::post('/documentos/assinar/{token}', [\App\Http\Controllers\Public\DocumentPublicSignatureController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('documents.public-sign.store');

// Convites de cadastro — wizard público do paciente (Fase 2, ver docs/PATIENT_INVITATIONS_BRD.md §8)
Route::get('/p/{token}', [\App\Http\Controllers\Public\PatientInvitePublicController::class, 'show'])->name('patient-invites.public.show');
Route::patch('/p/{token}', [\App\Http\Controllers\Public\PatientInvitePublicController::class, 'update'])
    ->middleware('throttle:60,1')
    ->name('patient-invites.public.update');
Route::post('/p/{token}/concluir', [\App\Http\Controllers\Public\PatientInvitePublicController::class, 'complete'])
    ->middleware('throttle:20,1')
    ->name('patient-invites.public.complete');
// Etapa de Anamnese (Fase 4, ver docs/PATIENT_INVITATIONS_BRD.md §11/§15)
Route::patch('/p/{token}/anamnese', [\App\Http\Controllers\Public\PatientInvitePublicController::class, 'updateAnamnesis'])
    ->middleware('throttle:60,1')
    ->name('patient-invites.public.anamnese.update');
Route::post('/p/{token}/anamnese/concluir', [\App\Http\Controllers\Public\PatientInvitePublicController::class, 'completeAnamnesis'])
    ->middleware('throttle:20,1')
    ->name('patient-invites.public.anamnese.complete');

// Rotas públicas
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
});

// Rotas autenticadas
Route::middleware(['auth', 'verified'])->group(function () {

    // Onboarding — 'clinic' aqui só serve para desviar contas Affiliate;
    // para usuários normais sem clínica ainda, o middleware não faz nada.
    Route::prefix('onboarding')->name('onboarding.')->middleware('clinic')->group(function () {
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
                    session(['current_clinic' => $clinic->toSessionPayload()]);
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
        Route::put('/patients/{patient}/responsible-professional', [\App\Http\Controllers\PatientController::class, 'updateResponsibleProfessional'])->name('patients.responsible-professional');
        Route::delete('/patients/{patient}', [\App\Http\Controllers\PatientController::class, 'destroy'])->name('patients.destroy');

        // Convites de cadastro (Fase 1 — ver docs/PATIENT_INVITATIONS_BRD.md)
        Route::prefix('patient-invites')->name('patient-invites.')->group(function () {
            Route::get('/check-phone', [\App\Http\Controllers\PatientInviteController::class, 'checkPhone'])->name('check-phone');
            Route::post('/', [\App\Http\Controllers\PatientInviteController::class, 'store'])->name('store');
            Route::get('/{invite}/qrcode', [\App\Http\Controllers\PatientInviteController::class, 'qrcode'])->name('qrcode');
            Route::post('/{invite}/resend', [\App\Http\Controllers\PatientInviteController::class, 'resend'])->name('resend');
            Route::post('/{invite}/cancel', [\App\Http\Controllers\PatientInviteController::class, 'cancel'])->name('cancel');
            Route::post('/{invite}/regenerate', [\App\Http\Controllers\PatientInviteController::class, 'regenerate'])->name('regenerate');
            Route::post('/{invite}/log-event', [\App\Http\Controllers\PatientInviteController::class, 'logEvent'])->name('log-event');
        });

        // Central de Anamneses
        Route::post('/patients/{patient}/anamneses', [\App\Http\Controllers\PatientAnamnesisController::class, 'create'])->name('patients.anamneses.create');
        Route::get('/patients/{patient}/anamneses/{anamnesis}', [\App\Http\Controllers\PatientAnamnesisController::class, 'show'])->name('patients.anamneses.show');
        Route::get('/patients/{patient}/anamneses/{anamnesis}/edit', [\App\Http\Controllers\PatientAnamnesisController::class, 'edit'])->name('patients.anamneses.edit');
        Route::put('/patients/{patient}/anamneses/{anamnesis}/answers', [\App\Http\Controllers\PatientAnamnesisController::class, 'saveAnswers'])->name('patients.anamneses.answers');
        Route::post('/patients/{patient}/anamneses/{anamnesis}/duplicate', [\App\Http\Controllers\PatientAnamnesisController::class, 'duplicate'])->name('patients.anamneses.duplicate');
        Route::get('/patients/{patient}/anamneses/{anamnesis}/pdf', [\App\Http\Controllers\PatientAnamnesisController::class, 'pdf'])->name('patients.anamneses.pdf');
        Route::post('/patients/{patient}/anamneses/{anamnesis}/toggle-question', [\App\Http\Controllers\PatientAnamnesisController::class, 'toggleQuestion'])->name('patients.anamneses.toggle-question');
        Route::post('/patients/{patient}/anamneses/{anamnesis}/add-question', [\App\Http\Controllers\PatientAnamnesisController::class, 'addInstanceQuestion'])->name('patients.anamneses.add-question');
        Route::patch('/patients/{patient}/anamneses/{anamnesis}/rename', [\App\Http\Controllers\PatientAnamnesisController::class, 'renameInstance'])->name('patients.anamneses.rename');
        Route::patch('/patients/{patient}/anamneses/{anamnesis}/date', [\App\Http\Controllers\PatientAnamnesisController::class, 'updateDate'])->name('patients.anamneses.update-date');
        Route::post('/patients/{patient}/anamneses/{anamnesis}/sign', [\App\Http\Controllers\AnamnesisSignatureController::class, 'store'])->name('patients.anamneses.sign');
        Route::post('/patients/{patient}/anamneses/{anamnesis}/sign-professional', [\App\Http\Controllers\AnamnesisSignatureController::class, 'storeDentist'])->name('patients.anamneses.sign-professional');
        Route::delete('/patients/{patient}/anamneses/{anamnesis}', [\App\Http\Controllers\PatientAnamnesisController::class, 'destroy'])->name('patients.anamneses.destroy');
        Route::get('/api/anamneses/pending-signatures', [\App\Http\Controllers\AnamnesisSignaturePendingController::class, 'counts'])->name('anamneses.pending-signatures');

        // Módulo Documentos — página principal e categorias
        Route::get('/documents', [\App\Http\Controllers\DocumentController::class, 'index'])->name('documents.index');
        Route::get('/documents/categories/{category}', [\App\Http\Controllers\DocumentController::class, 'category'])->name('documents.category');

        Route::get('/document-categories', [\App\Http\Controllers\DocumentCategoryController::class, 'index'])->name('document-categories.index');
        Route::post('/document-categories', [\App\Http\Controllers\DocumentCategoryController::class, 'store'])->name('document-categories.store');
        Route::put('/document-categories/{documentCategory}', [\App\Http\Controllers\DocumentCategoryController::class, 'update'])->name('document-categories.update');
        Route::post('/document-categories/{documentCategory}/deactivate', [\App\Http\Controllers\DocumentCategoryController::class, 'deactivate'])->name('document-categories.deactivate');

        // Modelos de Documentos (editor, versionamento)
        Route::get('/document-templates', [\App\Http\Controllers\DocumentTemplateController::class, 'index'])->name('document-templates.index');
        Route::get('/document-templates/create', [\App\Http\Controllers\DocumentTemplateController::class, 'create'])->name('document-templates.create');
        Route::post('/document-templates', [\App\Http\Controllers\DocumentTemplateController::class, 'store'])->name('document-templates.store');
        Route::post('/document-templates/preview', [\App\Http\Controllers\DocumentTemplatePreviewController::class, 'preview'])->name('document-templates.preview');
        Route::get('/document-templates/placeholders', [\App\Http\Controllers\DocumentTemplatePreviewController::class, 'placeholders'])->name('document-templates.placeholders');
        Route::get('/document-templates/{documentTemplate}/edit', [\App\Http\Controllers\DocumentTemplateController::class, 'edit'])->name('document-templates.edit');
        Route::put('/document-templates/{documentTemplate}', [\App\Http\Controllers\DocumentTemplateController::class, 'update'])->name('document-templates.update');
        Route::post('/document-templates/{documentTemplate}/duplicate', [\App\Http\Controllers\DocumentTemplateController::class, 'duplicate'])->name('document-templates.duplicate');
        Route::post('/document-templates/{documentTemplate}/archive', [\App\Http\Controllers\DocumentTemplateController::class, 'archive'])->name('document-templates.archive');
        Route::post('/document-templates/{documentTemplate}/set-default', [\App\Http\Controllers\DocumentTemplateController::class, 'setDefault'])->name('document-templates.set-default');
        Route::delete('/document-templates/{documentTemplate}', [\App\Http\Controllers\DocumentTemplateController::class, 'destroy'])->name('document-templates.destroy');

        // Documentos emitidos por paciente
        Route::post('/patients/{patient}/documents', [\App\Http\Controllers\PatientDocumentController::class, 'store'])->name('patients.documents.store');
        Route::get('/patients/{patient}/documents/{document}', [\App\Http\Controllers\PatientDocumentController::class, 'show'])->name('patients.documents.show');
        Route::get('/patients/{patient}/documents/{document}/pdf', [\App\Http\Controllers\PatientDocumentController::class, 'pdf'])->name('patients.documents.pdf');
        Route::post('/patients/{patient}/documents/{document}/cancel', [\App\Http\Controllers\PatientDocumentController::class, 'cancel'])->name('patients.documents.cancel');
        Route::delete('/patients/{patient}/documents/{document}', [\App\Http\Controllers\PatientDocumentController::class, 'destroy'])->name('patients.documents.destroy');
        Route::post('/patients/{patient}/documents/{document}/sign/{role}', [\App\Http\Controllers\DocumentSignatureController::class, 'store'])->name('patients.documents.sign');

        // Painel de assinaturas
        Route::get('/patients/{patient}/documents/{document}/signature-panel', [\App\Http\Controllers\DocumentSignaturePanelController::class, 'show'])->name('patients.documents.signature-panel');
        Route::post('/patients/{patient}/documents/{document}/signature-panel/generate-link', [\App\Http\Controllers\DocumentSignaturePanelController::class, 'generateLink'])->name('patients.documents.signature-panel.generate-link');
        Route::post('/patients/{patient}/documents/{document}/signature-panel/send-email', [\App\Http\Controllers\DocumentSignaturePanelController::class, 'sendEmail'])->name('patients.documents.signature-panel.send-email');
        Route::post('/patients/{patient}/documents/{document}/signature-panel/cancel', [\App\Http\Controllers\DocumentSignaturePanelController::class, 'cancel'])->name('patients.documents.signature-panel.cancel');
        Route::post('/patients/{patient}/documents/{document}/signature-panel/log-whatsapp', [\App\Http\Controllers\DocumentSignaturePanelController::class, 'logWhatsapp'])->name('patients.documents.signature-panel.log-whatsapp');

        // Configurações de Documentos da clínica
        Route::get('/clinic-settings/documents', [\App\Http\Controllers\ClinicDocumentSettingsController::class, 'edit'])->name('clinic-settings.documents.edit');
        Route::put('/clinic-settings/documents', [\App\Http\Controllers\ClinicDocumentSettingsController::class, 'update'])->name('clinic-settings.documents.update');

        // Observações do paciente
        Route::post('/patients/{patient}/notes', [\App\Http\Controllers\PatientNoteController::class, 'store'])->name('patients.notes.store');
        Route::put('/patients/{patient}/notes/{note}', [\App\Http\Controllers\PatientNoteController::class, 'update'])->name('patients.notes.update');
        Route::delete('/patients/{patient}/notes/{note}', [\App\Http\Controllers\PatientNoteController::class, 'destroy'])->name('patients.notes.destroy');

        // Marcadores administrativos do paciente
        Route::post('/markers', [\App\Http\Controllers\PatientMarkerController::class, 'store'])->name('markers.store');
        Route::put('/markers/{marker}', [\App\Http\Controllers\PatientMarkerController::class, 'update'])->name('markers.update');
        Route::delete('/markers/{marker}', [\App\Http\Controllers\PatientMarkerController::class, 'destroy'])->name('markers.destroy');
        Route::put('/patients/{patient}/markers', [\App\Http\Controllers\PatientMarkerController::class, 'sync'])->name('patients.markers.sync');

        // Modelos de Anamnese (administração)
        Route::get('/anamnesis-templates', [\App\Http\Controllers\AnamnesisTemplateController::class, 'index'])->name('anamnesis-templates.index');
        Route::get('/anamnesis-templates/create', [\App\Http\Controllers\AnamnesisTemplateController::class, 'create'])->name('anamnesis-templates.create');
        Route::post('/anamnesis-templates', [\App\Http\Controllers\AnamnesisTemplateController::class, 'store'])->name('anamnesis-templates.store');
        Route::get('/anamnesis-templates/{anamnesisTemplate}/edit', [\App\Http\Controllers\AnamnesisTemplateController::class, 'edit'])->name('anamnesis-templates.edit');
        Route::put('/anamnesis-templates/{anamnesisTemplate}', [\App\Http\Controllers\AnamnesisTemplateController::class, 'update'])->name('anamnesis-templates.update');
        Route::post('/anamnesis-templates/{anamnesisTemplate}/duplicate', [\App\Http\Controllers\AnamnesisTemplateController::class, 'duplicate'])->name('anamnesis-templates.duplicate');
        Route::post('/anamnesis-templates/{anamnesisTemplate}/deactivate', [\App\Http\Controllers\AnamnesisTemplateController::class, 'deactivate'])->name('anamnesis-templates.deactivate');
        Route::post('/anamnesis-templates/{anamnesisTemplate}/set-default', [\App\Http\Controllers\AnamnesisTemplateController::class, 'setDefault'])->name('anamnesis-templates.set-default');
        Route::delete('/anamnesis-templates/{anamnesisTemplate}', [\App\Http\Controllers\AnamnesisTemplateController::class, 'destroy'])->name('anamnesis-templates.destroy');
        Route::post('/anamnesis-templates/{anamnesisTemplate}/questions', [\App\Http\Controllers\AnamnesisTemplateController::class, 'attachQuestion'])->name('anamnesis-templates.questions.attach');
        Route::delete('/anamnesis-templates/{anamnesisTemplate}/questions/{questionId}', [\App\Http\Controllers\AnamnesisTemplateController::class, 'detachQuestion'])->name('anamnesis-templates.questions.detach');
        Route::post('/anamnesis-templates/{anamnesisTemplate}/questions/{questionId}/move', [\App\Http\Controllers\AnamnesisTemplateController::class, 'moveQuestion'])->name('anamnesis-templates.questions.move');

        Route::get('/anamnesis-categories', [\App\Http\Controllers\AnamnesisCategoryController::class, 'index'])->name('anamnesis-categories.index');
        Route::post('/anamnesis-categories', [\App\Http\Controllers\AnamnesisCategoryController::class, 'store'])->name('anamnesis-categories.store');
        Route::put('/anamnesis-categories/{anamnesisCategory}', [\App\Http\Controllers\AnamnesisCategoryController::class, 'update'])->name('anamnesis-categories.update');
        Route::post('/anamnesis-categories/{anamnesisCategory}/deactivate', [\App\Http\Controllers\AnamnesisCategoryController::class, 'deactivate'])->name('anamnesis-categories.deactivate');

        Route::get('/anamnesis-questions', [\App\Http\Controllers\AnamnesisQuestionController::class, 'index'])->name('anamnesis-questions.index');
        Route::post('/anamnesis-questions', [\App\Http\Controllers\AnamnesisQuestionController::class, 'store'])->name('anamnesis-questions.store');
        Route::put('/anamnesis-questions/{question}', [\App\Http\Controllers\AnamnesisQuestionController::class, 'update'])->name('anamnesis-questions.update');
        Route::post('/anamnesis-questions/{question}/duplicate', [\App\Http\Controllers\AnamnesisQuestionController::class, 'duplicate'])->name('anamnesis-questions.duplicate');
        Route::post('/anamnesis-questions/{question}/deactivate', [\App\Http\Controllers\AnamnesisQuestionController::class, 'deactivate'])->name('anamnesis-questions.deactivate');
        Route::post('/anamnesis-questions/{question}/toggle-active', [\App\Http\Controllers\AnamnesisQuestionController::class, 'toggleActive'])->name('anamnesis-questions.toggle-active');

        // Odontograma — página exclusiva
        Route::get('/patients/{patient}/odontogram', [\App\Http\Controllers\PatientOdontogramController::class, 'show'])->name('patients.odontogram');

        // Tratamentos (histórico odontológico por paciente — aba "Tratamentos" na ficha do paciente)
        Route::post('/patients/{patient}/treatments', [\App\Http\Controllers\PatientTreatmentController::class, 'store'])->name('patients.treatments.store');
        Route::put('/patients/{patient}/treatments/{patientTreatment}', [\App\Http\Controllers\PatientTreatmentController::class, 'update'])->name('patients.treatments.update');
        Route::post('/patients/{patient}/treatments/{patientTreatment}/cost', [\App\Http\Controllers\PatientTreatmentController::class, 'updateCost'])->name('patients.treatments.cost');
        Route::post('/patients/{patient}/treatments/{patientTreatment}/finalize', [\App\Http\Controllers\PatientTreatmentController::class, 'finalize'])->name('patients.treatments.finalize');
        Route::post('/patients/{patient}/treatments/{patientTreatment}/duplicate', [\App\Http\Controllers\PatientTreatmentController::class, 'duplicate'])->name('patients.treatments.duplicate');
        Route::delete('/patients/{patient}/treatments/{patientTreatment}', [\App\Http\Controllers\PatientTreatmentController::class, 'destroy'])->name('patients.treatments.destroy');

        // Evoluções clínicas (card na Visão Geral da ficha do paciente)
        Route::post('/patients/{patient}/evolutions', [\App\Http\Controllers\PatientEvolutionController::class, 'store'])->name('patients.evolutions.store');
        Route::post('/patients/{patient}/evolutions/photos/{photo}/retry', [\App\Http\Controllers\PatientEvolutionController::class, 'retryPhoto'])->name('patients.evolutions.photos.retry');
        Route::post('/patients/{patient}/evolutions/{evolution}/signature', [\App\Http\Controllers\PatientEvolutionSignatureController::class, 'store'])->name('patients.evolutions.signature.store');

        // Prontuário odontológico
        Route::get('/patients/{patient}/prontuario', [\App\Http\Controllers\PatientProntuarioController::class, 'show'])->name('patients.prontuario');
        Route::put('/patients/{patient}/prontuario/anamnesis', [\App\Http\Controllers\PatientProntuarioController::class, 'updateAnamnesis'])->name('patients.prontuario.anamnesis');
        Route::post('/patients/{patient}/prontuario/evolutions', [\App\Http\Controllers\PatientProntuarioController::class, 'storeEvolution'])->name('patients.prontuario.evolutions');
        Route::put('/patients/{patient}/prontuario/odontogram', [\App\Http\Controllers\PatientProntuarioController::class, 'updateOdontogram'])->name('patients.prontuario.odontogram');
        Route::get('/patients/{patient}/prontuario/pdf', [\App\Http\Controllers\PatientProntuarioController::class, 'generatePdf'])->name('patients.prontuario.pdf');

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
        Route::delete('/clinic-settings/logo', [\App\Http\Controllers\ClinicSettingsController::class, 'removeLogo'])->name('clinic-settings.logo.remove');

        // Convênios (usados no cadastro de paciente e no módulo de Tratamentos)
        Route::get('/clinic-settings/convenios', [\App\Http\Controllers\ConvenioController::class, 'index'])->name('clinic-settings.convenios.index');
        Route::post('/clinic-settings/convenios', [\App\Http\Controllers\ConvenioController::class, 'store'])->name('clinic-settings.convenios.store');
        Route::put('/clinic-settings/convenios/{convenio}', [\App\Http\Controllers\ConvenioController::class, 'update'])->name('clinic-settings.convenios.update');
        Route::post('/clinic-settings/convenios/{convenio}/toggle', [\App\Http\Controllers\ConvenioController::class, 'toggle'])->name('clinic-settings.convenios.toggle');

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
        Route::post('/treatments/{treatment}/default-cost', [\App\Http\Controllers\TreatmentController::class, 'updateDefaultCost'])->name('treatments.default-cost');
        Route::post('/treatments/{treatment}/deactivate', [\App\Http\Controllers\TreatmentController::class, 'deactivate'])->name('treatments.deactivate');
        Route::post('/treatments/{treatment}/reactivate', [\App\Http\Controllers\TreatmentController::class, 'reactivate'])->name('treatments.reactivate');
        Route::delete('/treatments/{treatment}', [\App\Http\Controllers\TreatmentController::class, 'destroy'])->name('treatments.destroy');
        // Estoque básico
        Route::get('/inventory', [\App\Http\Controllers\InventoryController::class, 'index'])->name('inventory.index');
        Route::get('/inventory/create', [\App\Http\Controllers\InventoryController::class, 'create'])->name('inventory.create');
        Route::post('/inventory', [\App\Http\Controllers\InventoryController::class, 'store'])->name('inventory.store');
        Route::post('/inventory/{item}/add-stock', [\App\Http\Controllers\InventoryController::class, 'addStock'])->name('inventory.add-stock');
        // Financeiro básico + Hub de Crédito
        Route::get('/finance', [\App\Http\Controllers\FinanceController::class, 'index'])->name('finance.index');
        Route::post('/finance/transactions', [\App\Http\Controllers\FinanceController::class, 'storeTransaction'])->name('finance.store-transaction');
        Route::post('/finance/pricing', [\App\Http\Controllers\FinanceController::class, 'updatePricing'])->name('finance.update-pricing');
        Route::post('/finance/budgets', [\App\Http\Controllers\FinanceController::class, 'createBudgetFromExecution'])->name('finance.create-budget');
        Route::get('/finance/marketplace', [\App\Http\Controllers\FinancialMarketplaceController::class, 'index'])->name('finance.marketplace');
        Route::post('/finance/marketplace/connections', [\App\Http\Controllers\FinancialMarketplaceController::class, 'store'])->name('finance.marketplace.store');
        Route::post('/finance/marketplace/{provider}/test', [\App\Http\Controllers\FinancialMarketplaceController::class, 'test'])->name('finance.marketplace.test');
        Route::post('/finance/budgets/{budget}/simulate-financing', [\App\Http\Controllers\FinancingSimulationController::class, 'simulate'])->name('finance.budgets.simulate');
        Route::post('/finance/budgets/{budget}/proposals', [\App\Http\Controllers\FinancingProposalController::class, 'store'])->name('finance.budgets.proposals');

        // Programa de Indicações
        Route::get('/indicacoes', [\App\Http\Controllers\ReferralController::class, 'index'])->name('referrals.index');
        Route::post('/indicacoes/pix', [\App\Http\Controllers\ReferralController::class, 'updatePix'])->name('referrals.pix');
        Route::post('/indicacoes/saque', [\App\Http\Controllers\ReferralController::class, 'requestWithdrawal'])->name('referrals.withdraw');
        Route::get('/indicacoes/exportar', [\App\Http\Controllers\ReferralController::class, 'exportCsv'])->name('referrals.export');
        Route::get('/indicacoes/{conversion}', [\App\Http\Controllers\ReferralController::class, 'show'])->name('referrals.show');

        // Notificações (polling)
        Route::get('/notifications/counts', function () {
            $now = \Illuminate\Support\Carbon::now();
            $clinicId = session('current_clinic_id');

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

            $referralNotifs = $clinicId
                ? \App\Models\AccessLog::where('clinic_id', $clinicId)
                    ->whereIn('action', [
                        'referral_trial_started',
                        'referral_plan_subscribed',
                        'referral_bonus_eligible',
                        'referral_payment_sent',
                    ])
                    ->where('created_at', '>=', now()->subDays(30))
                    ->latest('created_at')
                    ->limit(10)
                    ->get()
                    ->map(fn ($log) => [
                        'type' => match ($log->action) {
                            'referral_trial_started'    => 'info',
                            'referral_plan_subscribed'  => 'success',
                            'referral_bonus_eligible'   => 'success',
                            'referral_payment_sent'     => 'success',
                            default                     => 'info',
                        },
                        'text' => $log->description,
                    ])
                : collect();

            $referralCount = $referralNotifs->count();
            $clinicalTotal = $aguardandoConfirmacao + $aguardandoAtendimento + $esperando15min + $consultaProxima;

            return response()->json([
                'total'                  => $clinicalTotal + $referralCount,
                'aguardando_confirmacao' => $aguardandoConfirmacao,
                'aguardando_atendimento' => $aguardandoAtendimento,
                'esperando_15min'        => $esperando15min,
                'consulta_proxima'       => $consultaProxima,
                'referral_notifications' => $referralNotifs,
            ]);
        })->name('notifications.counts');

        // Profile
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::patch('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
        Route::delete('/profile/photo', [ProfileController::class, 'removePhoto'])->name('profile.photo.remove');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

        // Gestão de Equipe
        Route::get('/equipe', [\App\Http\Controllers\TeamController::class, 'index'])->name('team.index');
        Route::post('/equipe/membros/{user}/desativar', [\App\Http\Controllers\TeamController::class, 'deactivate'])->name('team.deactivate');
        Route::post('/equipe/membros/{user}/reativar', [\App\Http\Controllers\TeamController::class, 'reactivate'])->name('team.reactivate');
        Route::patch('/equipe/membros/{user}/cargo', [\App\Http\Controllers\TeamController::class, 'updateRole'])->name('team.update-role');

        // Convites — verificar cenário (deve vir antes de {invite} para evitar ambiguidade)
        Route::post('/equipe/convites/verificar', [\App\Http\Controllers\InviteController::class, 'check'])->name('invites.check');
        // Convites — CRUD + ações (todas retornam JSON)
        Route::post('/equipe/convites', [\App\Http\Controllers\InviteController::class, 'store'])->name('invites.store');
        Route::delete('/equipe/convites/{invite}', [\App\Http\Controllers\InviteController::class, 'destroy'])->name('invites.destroy');
        Route::post('/equipe/convites/{invite}/reenviar', [\App\Http\Controllers\InviteController::class, 'resend'])->name('invites.resend');
        Route::post('/equipe/convites/{invite}/novo-token', [\App\Http\Controllers\InviteController::class, 'regenerateToken'])->name('invites.regenerate');
        Route::post('/equipe/convites/{invite}/reativar', [\App\Http\Controllers\InviteController::class, 'reactivate'])->name('invites.reactivate');

        // Logs de Acesso
        Route::get('/logs-acesso', [\App\Http\Controllers\AccessLogController::class, 'index'])->name('access-logs.index');
        Route::get('/logs-acesso/exportar', [\App\Http\Controllers\AccessLogController::class, 'export'])->name('access-logs.export');

        // Fotos clínicas
        Route::post('/patients/{patient}/photos', [App\Http\Controllers\GoogleDriveController::class, 'uploadPhoto'])->name('patients.photos.upload');
        Route::get('/patients/{patient}/photos/{photo}', [App\Http\Controllers\GoogleDriveController::class, 'viewPhoto'])->name('patients.photos.view');
        Route::put('/patients/{patient}/photos/{photo}/rename', [App\Http\Controllers\GoogleDriveController::class, 'renamePhoto'])->name('patients.photos.rename');
        Route::delete('/patients/{patient}/photos/{photo}', [App\Http\Controllers\GoogleDriveController::class, 'deletePhoto'])->name('patients.photos.delete');
        Route::post('/drive/confirm-disclaimer', [App\Http\Controllers\GoogleDriveController::class, 'confirmDisclaimer'])->name('drive.confirm-disclaimer');
        Route::post('/patients/{patient}/drive/health-check', [App\Http\Controllers\GoogleDriveController::class, 'healthCheck'])->name('patients.drive.health-check');
        Route::post('/patients/{patient}/drive/verify', [App\Http\Controllers\GoogleDriveController::class, 'healthCheck'])->name('patients.drive.verify');
        Route::post('/patients/{patient}/drive/recover', [App\Http\Controllers\GoogleDriveController::class, 'recoverStructure'])->name('patients.drive.recover');

        // Checkout — assinatura paga via Stripe (Cashier)
        Route::get('/checkout/success', [App\Http\Controllers\CheckoutController::class, 'success'])->name('checkout.success');
        Route::get('/checkout/{plan:slug}', [App\Http\Controllers\CheckoutController::class, 'show'])->name('checkout.show');
        Route::post('/checkout/{plan:slug}', [App\Http\Controllers\CheckoutController::class, 'store'])->name('checkout.store');
    });

    // Painel do afiliado — contas Affiliate só acessam este grupo de rotas
    Route::prefix('afiliado')->name('affiliate.')->middleware('affiliate')->group(function () {
        Route::get('/', [\App\Http\Controllers\ReferralController::class, 'index'])->name('dashboard');
        Route::post('/pix', [\App\Http\Controllers\ReferralController::class, 'updatePix'])->name('pix');
        Route::post('/saque', [\App\Http\Controllers\ReferralController::class, 'requestWithdrawal'])->name('withdraw');
        Route::get('/exportar', [\App\Http\Controllers\ReferralController::class, 'exportCsv'])->name('export');
        Route::get('/{conversion}', [\App\Http\Controllers\ReferralController::class, 'show'])->name('show');
    });

    // Backoffice — Super Administrador
    Route::prefix('admin')->name('admin.')->middleware('super-admin')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('index');
        Route::post('/settings', [\App\Http\Controllers\Admin\DashboardController::class, 'updateSettings'])->name('settings');
        Route::get('/clinicas', [\App\Http\Controllers\Admin\DashboardController::class, 'clinics'])->name('clinics');
        Route::post('/clinicas/{clinic}/bloquear', [\App\Http\Controllers\Admin\DashboardController::class, 'blockClinic'])->name('clinics.block');
        Route::post('/clinicas/{clinic}/desbloquear', [\App\Http\Controllers\Admin\DashboardController::class, 'unblockClinic'])->name('clinics.unblock');
        Route::get('/indicacoes', [\App\Http\Controllers\Admin\DashboardController::class, 'referrals'])->name('referrals');
        Route::post('/afiliados/convidar', [\App\Http\Controllers\Admin\DashboardController::class, 'inviteAffiliate'])->name('affiliates.invite');
        Route::get('/planos', [\App\Http\Controllers\Admin\DashboardController::class, 'plans'])->name('plans');
        Route::put('/planos/{plan}', [\App\Http\Controllers\Admin\DashboardController::class, 'updatePlan'])->name('plans.update');
        Route::post('/pagamentos/{payment}/aprovar', [\App\Http\Controllers\Admin\DashboardController::class, 'approvePayment'])->name('payments.approve');
        Route::post('/pagamentos/{payment}/recusar', [\App\Http\Controllers\Admin\DashboardController::class, 'rejectPayment'])->name('payments.reject');
        Route::post('/indicacoes/{conversion}/estornar', [\App\Http\Controllers\Admin\DashboardController::class, 'refundConversion'])->name('referrals.refund');
        Route::post('/indicacoes/{conversion}/revisar', [\App\Http\Controllers\Admin\DashboardController::class, 'reviewConversion'])->name('referrals.review');
        Route::get('/logs', [\App\Http\Controllers\Admin\DashboardController::class, 'logs'])->name('logs');
    });
});

// Convite público (aceite de convite de equipe — sem auth obrigatória)
Route::get('/convites/{token}', [\App\Http\Controllers\InviteController::class, 'show'])->name('invites.show');
Route::post('/convites/{token}/aceitar', [\App\Http\Controllers\InviteController::class, 'accept'])->name('invites.accept');

// Webhooks financeiros — sem auth de sessão; validação por assinatura HMAC
Route::post('/webhooks/financial/{provider}/{connectionId}', [
    App\Http\Controllers\FinancialWebhookController::class,
    'receive',
])->name('financial.webhooks.receive');

// Webhook do Stripe (Cashier) — público, autenticado por assinatura do Stripe
Route::post('/stripe/webhook', [\App\Http\Controllers\StripeWebhookController::class, 'handleWebhook'])->name('cashier.webhook');

// Google Drive OAuth — fora do auth para suportar o redirect loop do Google,
// mas callback valida Auth::user() internamente.
Route::middleware('auth')->group(function () {
    Route::get('/auth/google', [App\Http\Controllers\GoogleDriveController::class, 'connect'])->name('google.connect');
    Route::get('/auth/google/callback', [App\Http\Controllers\GoogleDriveController::class, 'callback'])->name('google.callback');
    Route::post('/auth/google/disconnect/{clinic}', [App\Http\Controllers\GoogleDriveController::class, 'disconnect'])->name('google.disconnect');
});
