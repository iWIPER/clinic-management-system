<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\PatientOdontogram;
use App\Models\PatientTreatment;
use App\Services\PatientHubService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PatientOdontogramController extends Controller
{
    public function show(Request $request, Patient $patient, PatientHubService $hubService)
    {
        $this->authorize('view', $patient);

        $patient->load('photos');

        $odontogram = $patient->odontogram ?? PatientOdontogram::make([
            'patient_id' => $patient->id,
            'clinic_id'  => $patient->clinic_id,
            'teeth_data' => PatientOdontogram::defaultTeethData(),
        ]);

        $clinic           = $patient->clinic;
        $isDriveConnected = $clinic
            && $clinic->storageConnection
            && $clinic->storageConnection->status === 'connected';

        $responsibleTeam = Appointment::where('patient_id', $patient->id)
            ->whereNotIn('status', ['cancelled'])
            ->with('professional:id,name,job_title')
            ->get()
            ->pluck('professional')
            ->filter()
            ->unique('id')
            ->map(fn ($u) => [
                'id'        => $u->id,
                'name'      => $u->name,
                'job_title' => $u->job_title,
            ])
            ->values();

        $lastAppointment = Appointment::where('patient_id', $patient->id)
            ->whereIn('status', ['completed', 'confirmed', 'no_show'])
            ->latest('start')
            ->first();

        $activePhotos = $patient->photos
            ->filter(fn ($p) => !in_array($p->status, ['removed', 'missing']))
            ->values();

        return Inertia::render('Patients/Odontogram', [
            'patient'           => $patient->only([
                'id', 'nome', 'sobrenome', 'nascimento', 'doc_tipo', 'doc_numero',
                'telefone', 'email', 'status',
            ]),
            'odontogram'        => $odontogram,
            'toothStatuses'     => collect(PatientOdontogram::TOOTH_STATUSES)
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values(),
            'treatmentsByTooth' => PatientTreatment::groupedByTooth($patient->id),
            'hub'               => $hubService->build($patient),
            'photos'            => $activePhotos,
            'isDriveConnected'  => $isDriveConnected,
            'responsibleTeam'   => $responsibleTeam,
            'lastAppointmentAt' => $lastAppointment?->start,
            'initialPhotoId'    => $request->integer('photo_id') ?: null,
        ]);
    }
}
