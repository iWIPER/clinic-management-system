<?php

namespace App\Services;

use App\Mail\PatientInviteMail;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\PatientInvite;
use App\Models\PatientInviteActivityLog;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PatientInviteService
{
    const DEFAULT_EXPIRY_DAYS = 7;

    // ── Detecção de telefone duplicado (BRD §7) ─────────────────────────────
    public function findPatientByPhone(string $telefone, int $clinicId): ?Patient
    {
        return Patient::where('clinic_id', $clinicId)
            ->where('telefone', $telefone)
            ->first();
    }

    public function findActiveInvite(int $patientId, string $kind): ?PatientInvite
    {
        return PatientInvite::where('patient_id', $patientId)
            ->where('kind', $kind)
            ->whereNotIn('status', PatientInvite::TERMINAL_STATUSES)
            ->first();
    }

    // ── Fluxo do paciente (BRD §8) — busca por token, nunca por ID ─────────

    /**
     * Busca por token (nunca por ID numérico — rota pública, BRD §16) e já
     * normaliza a expiração na hora: não confia só no job agendado da Fase 5
     * para detectar vencimento, mesmo cuidado de DocumentPublicSignatureController.
     */
    public function findByToken(string $token): ?PatientInvite
    {
        $invite = PatientInvite::where('token', $token)->first();

        if ($invite) {
            $this->expireIfNeeded($invite);
        }

        return $invite;
    }

    /**
     * null = convite utilizável. Caso contrário, o motivo já mapeado para o
     * `reason` que PatientInvites/Invalid.vue espera (BRD §8.3) — token
     * inexistente cai em "expired" também, nunca vira 404 (BRD §16).
     */
    public function invalidReason(?PatientInvite $invite): ?string
    {
        if (! $invite) {
            return 'expired';
        }

        return match ($invite->status) {
            'cancelado' => 'cancelled',
            'expirado'  => 'expired',
            default     => null,
        };
    }

    /**
     * Idempotente — só a primeira abertura conta (mesmo cuidado de
     * opened_at/current_step: nunca escrever no banco só por uma leitura).
     */
    public function markOpenedIfNeeded(PatientInvite $invite): void
    {
        if ($invite->opened_at) {
            return;
        }

        $invite->update(['opened_at' => now(), 'status' => 'visualizado']);
        $this->log($invite, 'opened', 'patient');
    }

    /**
     * Autosave de um subconjunto de campos do Patient (BRD §8.1). A allowlist
     * e a validação relaxada já foram resolvidas pelo controller — este
     * método só persiste o que já chegou validado, avança current_step e
     * recalcula progress.
     */
    public function saveDraftFields(PatientInvite $invite, array $validatedFields, ?string $step = null): Patient
    {
        $patient = $invite->patient;

        if ($validatedFields !== []) {
            $patient->update($validatedFields);
        }

        $updates = [];

        if (in_array($invite->status, ['gerado', 'enviado', 'visualizado'], true)) {
            $updates['status'] = 'em_preenchimento';
        }

        if (! $invite->started_at) {
            $updates['started_at'] = now();
        }

        if ($step !== null && $step !== $invite->current_step) {
            // Único evento de etapa nomeado no BRD §10 para as etapas desta
            // fase — endereço/responsável legal não têm um "concluído"
            // próprio definido lá, então não inventamos um aqui. "Convênio
            // concluído" (insurance_step_completed) é logado em complete(),
            // não aqui — "convenio" é sempre a última etapa quando existe,
            // então sua transição de saída é a própria conclusão do cadastro.
            if ($invite->current_step === 'dados_pessoais') {
                $this->log($invite, 'personal_data_completed', 'patient');
            }
            $updates['current_step'] = $step;
        }

        // max() de propósito: current_step sempre reflete a posição literal
        // atual (necessário para a retomada exata, BRD §8.1), mas o
        // percentual mostrado à recepção (§9) não pode regredir só porque o
        // paciente voltou para revisar/corrigir uma etapa anterior — os
        // dados das etapas seguintes continuam salvos, não foram desfeitos.
        $updates['progress'] = max($invite->progress, $this->calculateProgress($invite, $step ?? $invite->current_step));

        $invite->update($updates);

        return $patient->fresh();
    }

    /**
     * Finaliza o cadastro (Dados pessoais/Endereço/Responsável legal, e
     * Convênio quando allow_insurance). Decisão confirmada com o usuário:
     * mesmo com allow_anamnesis=true, conclui direto — a etapa de anamnese
     * ainda não existe (Fase 4). Quando existir, este método precisa ganhar
     * de volta a ramificação para "aguardando_conclusao" nesse caso.
     */
    public function complete(PatientInvite $invite): array
    {
        // "convenio" é sempre a última etapa quando allow_insurance é
        // verdadeiro (Fase 3) — chegar em complete() já implica tê-la
        // percorrido, então é aqui que "Convênio concluído" é logado (BRD
        // §10), não em saveDraftFields() (não há uma etapa seguinte para
        // onde current_step avançaria e disparasse esse log de lá).
        if ($invite->allow_insurance) {
            $this->log($invite, 'insurance_step_completed', 'patient');
        }

        $invite->update([
            'status'       => 'concluido',
            'completed_at' => now(),
            'progress'     => 100,
        ]);

        $this->log($invite, 'completed', 'patient');

        return $this->completionPayload($invite);
    }

    /**
     * Extraído de complete() para ser reaproveitado por show() também: um
     * convite já concluído que é reaberto (segunda aba, outro dispositivo,
     * F5) deve mostrar a mesma tela de conclusão de novo, com o mesmo
     * lookup de agendamento — não recalculado com uma lógica paralela.
     */
    public function completionPayload(PatientInvite $invite): array
    {
        $nextAppointment = Appointment::where('patient_id', $invite->patient_id)
            ->where('start', '>=', now())
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->orderBy('start')
            ->first();

        return [
            'next_appointment' => $nextAppointment ? [
                'start' => $nextAppointment->start->toIso8601String(),
            ] : null,
        ];
    }

    /**
     * Cria um convite (e o Patient, se ainda não existir). Se já houver um
     * convite ativo para o mesmo paciente+tipo, cancela-o automaticamente
     * antes de criar o novo (BRD §5.2) — nunca deixa dois ativos coexistindo,
     * mesma regra que o índice único da migration garante em último caso.
     */
    public function create(array $data, int $clinicId, int $createdById): PatientInvite
    {
        return DB::transaction(function () use ($data, $clinicId, $createdById) {
            $patient = $this->resolvePatient($data, $clinicId, $createdById);

            $existing = PatientInvite::where('patient_id', $patient->id)
                ->where('kind', $data['kind'])
                ->whereNotIn('status', PatientInvite::TERMINAL_STATUSES)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $this->cancel($existing, autoCancelled: true, cancelledById: $createdById);
            }

            $invite = PatientInvite::create([
                'clinic_id'             => $clinicId,
                'patient_id'            => $patient->id,
                'kind'                  => $data['kind'],
                'token'                 => Str::random(40),
                'status'                => 'gerado',
                'channel'               => $data['channel'],
                'allow_insurance'       => $data['allow_insurance'] ?? false,
                'allow_anamnesis'       => $data['allow_anamnesis'] ?? false,
                'anamnesis_template_id' => $data['anamnesis_template_id'] ?? null,
                'expires_at'            => $this->resolveExpiresAt($data['expires_in_days'] ?? null),
                'created_by'            => $createdById,
            ]);

            $this->log($invite, 'invite_created', 'staff', $createdById);

            if ($invite->channel === 'email') {
                $this->sendEmailAndMarkSent($invite, $patient);
            }

            return $invite->fresh();
        });
    }

    /**
     * Sempre gera um convite novo (nunca reaproveita o token anterior — BRD
     * §5.1). Reaproveita as mesmas configurações do convite de origem;
     * expiração recomeça do zero (padrão de 7 dias, mesmo default de Invite).
     */
    public function regenerate(PatientInvite $invite, int $userId, ?int $expiresInDays = self::DEFAULT_EXPIRY_DAYS): PatientInvite
    {
        return $this->create([
            'kind'                  => $invite->kind,
            'existing_patient_id'   => $invite->patient_id,
            'channel'               => $invite->channel,
            'allow_insurance'       => $invite->allow_insurance,
            'allow_anamnesis'       => $invite->allow_anamnesis,
            'anamnesis_template_id' => $invite->anamnesis_template_id,
            'expires_in_days'       => $expiresInDays,
        ], $invite->clinic_id, $userId);
    }

    public function resend(PatientInvite $invite, int $userId): array
    {
        $patient = $invite->patient;

        if ($invite->channel === 'email') {
            $this->sendEmailAndMarkSent($invite, $patient);
        }

        $this->log($invite, 'resent', 'staff', $userId);

        return $this->buildShareData($invite);
    }

    public function cancel(PatientInvite $invite, bool $autoCancelled = false, ?int $cancelledById = null): void
    {
        $invite->update([
            'status'       => 'cancelado',
            'cancelled_at' => now(),
            'cancelled_by' => $cancelledById,
        ]);

        $this->log(
            $invite,
            $autoCancelled ? 'auto_cancelled_by_new_invite' : 'cancelled',
            'staff',
            $cancelledById,
        );
    }

    /**
     * Eventos disparados pelo frontend que não têm uma rota própria (BRD
     * §10) — "Link copiado" e "WhatsApp enviado". Este último também marca o
     * convite como enviado (aproximação: sabemos que o botão foi clicado,
     * não que a mensagem foi de fato entregue — BRD §5, nota sobre WhatsApp).
     */
    public function logFrontendEvent(PatientInvite $invite, string $action, int $userId): void
    {
        if (! in_array($action, ['link_copied', 'whatsapp_link_generated'], true)) {
            abort(422, 'Ação inválida.');
        }

        if ($action === 'whatsapp_link_generated' && $invite->status === 'gerado') {
            $invite->update(['status' => 'enviado']);
        }

        $this->log($invite, $action, 'staff', $userId);
    }

    public function buildShareData(PatientInvite $invite): array
    {
        $url = $this->buildInviteUrl($invite);

        return [
            'url'          => $url,
            'qrcode_url'   => route('patient-invites.qrcode', $invite),
            'whatsapp_url' => $this->buildWhatsAppUrl($invite->patient, $url),
        ];
    }

    // A rota pública /p/{token} só existe a partir da Fase 2 (BRD §19) — o
    // link já é gerado e comunicado nesta fase, mas só resolve de fato quando
    // o wizard for implementado.
    public function buildInviteUrl(PatientInvite $invite): string
    {
        return rtrim(config('app.url'), '/') . '/p/' . $invite->token;
    }

    public function generateQrCodeSvg(string $content): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(300),
            new SvgImageBackEnd(),
        );

        return (new Writer($renderer))->writeString($content);
    }

    private function resolvePatient(array $data, int $clinicId, int $createdById): Patient
    {
        if (! empty($data['existing_patient_id'])) {
            return Patient::where('clinic_id', $clinicId)->findOrFail($data['existing_patient_id']);
        }

        $creator = User::findOrFail($createdById);

        return Patient::create([
            'clinic_id'                    => $clinicId,
            'nome'                         => $data['nome'],
            'sobrenome'                    => $data['sobrenome'],
            'telefone'                     => $data['telefone'],
            'email'                        => $data['email'] ?? null,
            'status'                       => 'ativo',
            'origem'                       => 'convite',
            'created_by_id'                => $createdById,
            'updated_by_id'                => $createdById,
            // Mesma regra de PatientController::store(): só autoatribui o
            // profissional responsável quando quem cadastra é Dentista.
            'responsible_professional_id'  => $creator->job_title === 'Dentista' ? $createdById : null,
        ]);
    }

    private function resolveExpiresAt(?int $days): ?\Illuminate\Support\Carbon
    {
        return $days ? now()->addDays($days) : null;
    }

    private function expireIfNeeded(PatientInvite $invite): void
    {
        if (! $invite->isActive() || ! $invite->expires_at || ! $invite->expires_at->isPast()) {
            return;
        }

        $invite->update(['status' => 'expirado']);
        $this->log($invite, 'expired', 'staff');
    }

    // Baseado na posição da etapa atual em $invite->wizardSteps() — lista
    // por convite, não uma constante fixa (Fase 3: "convenio" só conta para
    // quem tem allow_insurance, senão o denominador ficaria errado e esse
    // convite nunca chegaria a 100%). Não inspeciona campos do Patient
    // diretamente (ex.: possui_responsavel_legal tem default false na
    // coluna, indistinguível de "ainda não respondeu" até o paciente de fato
    // passar pela etapa 3) — a etapa em si já é o sinal confiável.
    private function calculateProgress(PatientInvite $invite, ?string $currentStep): int
    {
        $steps = $invite->wizardSteps();
        $index = array_search($currentStep, $steps, true);

        if ($index === false) {
            return 0;
        }

        return (int) round($index / count($steps) * 100);
    }

    private function sendEmailAndMarkSent(PatientInvite $invite, Patient $patient): void
    {
        $mailerDriver = config('mail.default', 'log');
        $url          = $this->buildInviteUrl($invite);

        try {
            Mail::to($patient->email, $patient->nome)->send(new PatientInviteMail($invite, $url));

            Log::info('[PatientInviteService] E-mail de convite enviado', [
                'invite_id' => $invite->id,
                'email'     => $patient->email,
                'mailer'    => $mailerDriver,
            ]);

            $invite->update(['status' => 'enviado']);
            $this->log($invite, 'email_sent', 'staff', $invite->created_by, ['mailer' => $mailerDriver]);
        } catch (\Throwable $e) {
            Log::error('[PatientInviteService] Falha ao enviar e-mail de convite', [
                'invite_id' => $invite->id,
                'email'     => $patient->email,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    // Mesmo padrão já usado em PatientHubService::birthdayInfo() — link de
    // clique manual, sem confirmação real de entrega (BRD §3.7).
    private function buildWhatsAppUrl(Patient $patient, string $url): ?string
    {
        if (! $patient->telefone) {
            return null;
        }

        $message = "Olá {$patient->nome}! Segue o link para você completar seu cadastro na clínica: {$url}";

        return 'https://wa.me/55' . preg_replace('/\D/', '', $patient->telefone) . '?text=' . urlencode($message);
    }

    private function log(PatientInvite $invite, string $action, string $actorType, ?int $userId = null, ?array $metadata = null): void
    {
        PatientInviteActivityLog::create([
            'clinic_id'          => $invite->clinic_id,
            'patient_invite_id'  => $invite->id,
            'action'             => $action,
            'metadata'           => $metadata,
            'actor_type'         => $actorType,
            'user_id'            => $userId,
            'created_at'         => now(),
        ]);
    }
}
