<?php

namespace App\Services;

use App\Models\AccessLog;
use App\Models\SystemAdmin;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Fase System Admin/Backoffice — exclusão de conta de usuário, mapeada
 * contra o schema real (consultado via information_schema, não só leitura
 * de migration): 54 foreign keys apontam pra users.id. A maioria já é
 * SET NULL (anonimização automática pelo próprio banco). Doze são CASCADE
 * com coluna NOT NULL — entre elas clinical_records.professional_id,
 * clinical_evolutions.professional_id, consultations.professional_id,
 * appointments.professional_id, anamnesis_instances.professional_id.
 *
 * Rodar User::delete() direto nessas condições apagaria prontuário,
 * evolução clínica, consulta e anamnese de qualquer profissional com
 * histórico — dado de saúde de paciente, não just "dado do usuário".
 *
 * Por isso dois caminhos, nunca um terceiro escondido:
 *
 * A) EXCLUSÃO FÍSICA — só quando comprovadamente seguro: o usuário nunca
 *    teve nenhuma linha nas tabelas NOT NULL+CASCADE listadas acima. Nesse
 *    caso User::delete() só aciona SET NULL (preserva histórico de forma
 *    correta) ou CASCADE inofensivo (clinic_user, task_lists/tasks/
 *    comments do próprio usuário, user_profile_activity_logs).
 *
 * B) ANONIMIZAÇÃO — caminho padrão pra qualquer usuário com histórico
 *    clínico real: a linha em users permanece (satisfaz toda FK NOT NULL),
 *    PII é zerada (nome, e-mail, telefone, CPF, foto), a conta nunca mais
 *    consegue logar, e o usuário sai de todas as clínicas.
 */
class UserRemovalService
{
    /**
     * Tabelas/colunas onde o FK é NOT NULL + CASCADE — presença de
     * qualquer linha aqui bloqueia a exclusão física (força anonimização).
     */
    private const CLINICAL_HISTORY_TABLES = [
        ['clinical_records', 'professional_id'],
        ['clinical_evolutions', 'professional_id'],
        ['consultations', 'professional_id'],
        ['appointments', 'professional_id'],
        ['anamnesis_instances', 'professional_id'],
        ['patient_invites', 'created_by'],
        ['patient_notes', 'author_id'],
    ];

    /**
     * @throws ValidationException
     */
    public function remove(User $target, User $actingAdmin): string
    {
        $this->assertNotLastSystemAdmin($target);
        $this->assertNotSoleClinicOwner($target);

        if ($this->hasClinicalHistory($target)) {
            $this->anonymize($target, $actingAdmin);

            return 'anonymized';
        }

        $this->deletePhysically($target, $actingAdmin);

        return 'deleted';
    }

    private function assertNotLastSystemAdmin(User $target): void
    {
        if (! $target->isSystemAdmin()) {
            return;
        }

        if (SystemAdmin::active()->count() <= 1) {
            throw ValidationException::withMessages(['user' => 'Não é possível excluir o último System Admin da plataforma. Promova outro administrador antes.']);
        }
    }

    private function assertNotSoleClinicOwner(User $target): void
    {
        $ownedClinics = $target->clinics()->wherePivot('role', 'owner')->get();

        if ($ownedClinics->isNotEmpty()) {
            throw ValidationException::withMessages([
                'user' => 'Este usuário é dono de uma ou mais clínicas (' . $ownedClinics->pluck('name')->implode(', ') . '). Transfira a titularidade antes de excluir a conta.',
            ]);
        }
    }

    private function hasClinicalHistory(User $target): bool
    {
        foreach (self::CLINICAL_HISTORY_TABLES as [$table, $column]) {
            if (DB::table($table)->where($column, $target->id)->exists()) {
                return true;
            }
        }

        return false;
    }

    private function anonymize(User $target, User $actingAdmin): void
    {
        DB::transaction(function () use ($target, $actingAdmin) {
            $originalEmail = $target->email;

            $target->forceFill([
                'name'                => 'Usuário removido',
                'email'               => 'removido+' . $target->id . '-' . Str::random(8) . '@wildental.invalid',
                'phone'               => null,
                'cpf'                 => null,
                'birth_date'          => null,
                'cro'                 => null,
                'cro_uf'              => null,
                'profile_photo_path'  => null,
                'preferences'         => null,
                'status'              => 'inativo',
                'password'            => bcrypt(Str::random(64)),
                'remember_token'      => null,
            ])->save();

            $target->clinics()->detach();

            AccessLog::record(
                action: 'admin_user_anonymized',
                description: "Conta de usuário anonimizada pelo administrador da plataforma (e-mail original oculto)",
                metadata: ['target_user_id' => $target->id, 'acting_admin_id' => $actingAdmin->id, 'original_email_hash' => hash('sha256', $originalEmail)],
                userId: $actingAdmin->id,
            );
        });
    }

    private function deletePhysically(User $target, User $actingAdmin): void
    {
        $targetId   = $target->id;
        $targetName = $target->name;

        DB::transaction(function () use ($target, $actingAdmin, $targetId, $targetName) {
            $target->clinics()->detach();
            $target->delete();

            AccessLog::record(
                action: 'admin_user_deleted',
                description: "Conta de {$targetName} excluída definitivamente pelo administrador da plataforma",
                metadata: ['target_user_id' => $targetId, 'acting_admin_id' => $actingAdmin->id],
                userId: $actingAdmin->id,
            );
        });
    }
}
