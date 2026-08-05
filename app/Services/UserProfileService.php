<?php

namespace App\Services;

use App\Models\ClinicalRecord;
use App\Models\Consultation;
use App\Models\Invite;
use App\Models\User;
use App\Models\UserProfileActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;

class UserProfileService
{
    private const ROLE_LABELS = [
        'owner'        => 'Administrador',
        'admin'        => 'Administrador',
        'professional' => 'Dentista',
        'staff'        => 'Recepcionista',
    ];

    private const GENDER_LABELS = [
        'masculino'              => 'Masculino',
        'feminino'               => 'Feminino',
        'outro'                  => 'Outro',
        'prefiro_nao_informar'   => 'Prefiro não informar',
    ];

    public function defaultPreferences(): array
    {
        return [
            'locale'                  => 'pt-BR',
            'date_format'             => 'DD/MM/YYYY',
            'currency_format'         => 'BRL',
            'timezone'                => 'America/Sao_Paulo',
            'notifications_email'     => true,
            'notifications_system'    => true,
            'notifications_whatsapp'  => false,
        ];
    }

    public function toPageData(User $user, ?int $currentClinicId): array
    {
        $clinicPivot = $currentClinicId
            ? $user->clinics()->where('clinics.id', $currentClinicId)->first()
            : null;

        $clinicRole = $clinicPivot?->pivot->role;
        $preferences = array_merge($this->defaultPreferences(), $user->preferences ?? []);

        if ($currentClinicId) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($currentClinicId);
        }

        $permissions = $user->getAllPermissions();
        $primaryClinic = $user->clinics()->orderBy('clinic_user.created_at')->first();

        return [
            'personal' => [
                'name'        => $user->name,
                'email'       => $user->email,
                'phone'       => $user->phone,
                'cpf'         => $user->cpf,
                'birth_date'  => $user->birth_date?->format('Y-m-d'),
                'gender'      => $user->gender,
                'gender_label'=> self::GENDER_LABELS[$user->gender] ?? null,
                'cro'         => $user->cro,
                'cro_uf'      => $user->cro_uf,
                'specialty'   => $user->specialty,
                'job_title'   => $user->job_title,
                'status'      => $user->status ?? 'ativo',
            ],
            'header' => [
                'name'           => $user->name,
                'job_title'      => $user->job_title,
                'clinic_name'    => $clinicPivot?->trade_name ?: $clinicPivot?->name,
                'specialty'      => $user->specialty,
                'cro'            => $this->formatCroDisplay($user->cro, $user->cro_uf),
                'status'         => $user->status ?? 'ativo',
                'last_login_at'  => $user->last_login_at?->toIso8601String(),
                'avatar_url'     => UserAvatarService::url($user),
                'initials'       => $this->initials($user->name),
            ],
            'history' => [
                'created_at'         => $user->created_at?->toIso8601String(),
                'updated_at'         => $user->updated_at?->toIso8601String(),
                'last_login_at'      => $user->last_login_at?->toIso8601String(),
                'profile_updated_at' => $user->profile_updated_at?->toIso8601String(),
                'active_days'        => $user->created_at
                    ? (int) $user->created_at->diffInDays(now())
                    : 0,
            ],
            'permissions' => [
                'role'                => $clinicRole,
                'role_label'          => self::ROLE_LABELS[$clinicRole] ?? ($clinicRole ? ucfirst($clinicRole) : null),
                'permissions_count'   => $permissions->count(),
                'clinics_count'       => $user->clinics()->count(),
                'primary_clinic'      => $primaryClinic
                    ? ($primaryClinic->trade_name ?: $primaryClinic->name)
                    : null,
                'can_edit_job_title'  => in_array($clinicRole, ['owner', 'admin']),
            ],
            'job_titles' => Invite::JOB_TITLES,
            'statistics' => $this->buildStatistics($user),
            'preferences' => $preferences,
            'must_verify_email' => $user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail
                && ! $user->hasVerifiedEmail(),
        ];
    }

    public function logChange(User $user, string $action, ?string $field, Request $request, array $metadata = []): void
    {
        UserProfileActivityLog::create([
            'user_id'    => $user->id,
            'action'     => $action,
            'field'      => $field,
            'ip_address' => $request->ip(),
            'metadata'   => $metadata ?: null,
            'created_at' => now(),
        ]);
    }

    public function logProfileChanges(User $user, array $original, array $updated, Request $request): void
    {
        $fieldActions = [
            'name'       => 'Nome alterado',
            'email'      => 'Email alterado',
            'phone'      => 'Telefone alterado',
            'cpf'        => 'CPF alterado',
            'birth_date' => 'Data de nascimento alterada',
            'gender'     => 'Gênero alterado',
            'cro'        => 'CRO alterado',
            'cro_uf'     => 'UF do CRO alterada',
            'specialty'  => 'Especialidade alterada',
            'job_title'  => 'Cargo alterado',
        ];

        foreach ($fieldActions as $field => $action) {
            $old = $original[$field] ?? null;
            $new = $updated[$field] ?? null;

            if ($this->normalizeValue($old) !== $this->normalizeValue($new)) {
                $this->logChange($user, $action, $field, $request);
            }
        }
    }

    private function buildStatistics(User $user): array
    {
        $consultationsQuery = Consultation::query()
            ->where('professional_id', $user->id)
            ->where('status', 'finalizado');

        $consultationsCount = (clone $consultationsQuery)->count();

        $patientsCount = $consultationsCount > 0
            ? (clone $consultationsQuery)->distinct('patient_id')->count('patient_id')
            : null;

        $proceduresCount = $user->id
            ? Consultation::query()
                ->where('professional_id', $user->id)
                ->whereHas('procedureExecutions')
                ->count()
            : null;

        $documentsCount = ClinicalRecord::query()
            ->where('professional_id', $user->id)
            ->whereNotNull('pdf_path')
            ->count();

        $lastConsultation = Consultation::query()
            ->where('professional_id', $user->id)
            ->whereNotNull('finished_at')
            ->max('finished_at');

        $lastRecord = ClinicalRecord::query()
            ->where('professional_id', $user->id)
            ->whereNotNull('finished_at')
            ->max('finished_at');

        $lastActivity = collect([
            $lastConsultation,
            $lastRecord,
            $user->profile_updated_at,
            $user->last_login_at,
        ])->filter()->map(fn ($d) => Carbon::parse($d))->max();

        $usageDays = $user->created_at
            ? (int) $user->created_at->diffInDays(now()) + 1
            : null;

        return [
            'consultations'  => $consultationsCount ?: null,
            'patients'       => $patientsCount ?: null,
            'procedures'     => $proceduresCount ?: null,
            'usage_days'     => $usageDays,
            'documents'      => $documentsCount ?: null,
            'last_activity'  => $lastActivity?->toIso8601String(),
        ];
    }

    private function formatCroDisplay(?string $cro, ?string $uf): ?string
    {
        if (! $cro) {
            return null;
        }

        return $uf ? "CRO/{$uf} {$cro}" : $cro;
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        if (count($parts) === 0) {
            return '?';
        }

        if (count($parts) === 1) {
            return strtoupper(substr($parts[0], 0, 2));
        }

        return strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
    }

    private function normalizeValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return (string) $value;
    }
}