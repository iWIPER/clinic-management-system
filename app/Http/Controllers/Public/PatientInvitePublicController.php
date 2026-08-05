<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PatientController;
use App\Models\Convenio;
use App\Models\PatientInvite;
use App\Services\PatientInviteService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PatientInvitePublicController extends Controller
{
    // Campos que o wizard público pode escrever (BRD §8 — Dados pessoais,
    // Endereço, Responsável legal/Contato de emergência, mesma exclusividade
    // mútua de Create.vue). Nunca inclui status, origem, created_by_id etc.,
    // mesmo que venham no payload — é a fronteira de segurança de um
    // endpoint sem autenticação.
    const DRAFT_FIELDS = [
        'nome', 'sobrenome', 'nascimento', 'sexo', 'is_estrangeiro', 'cpf', 'rg', 'passaporte',
        'profissao', 'canal_lembrete', 'telefone', 'email',
        'possui_responsavel_legal', 'responsavel_legal_nome', 'responsavel_legal_cpf',
        'responsavel_legal_rg', 'responsavel_legal_estrangeiro', 'responsavel_legal_passaporte',
        'responsavel_legal_telefone', 'responsavel_legal_parentesco',
        'contato_emergencia_nome', 'contato_emergencia_telefone',
        'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'estado',
    ];

    // Só entram na allowlist quando o convite tem allow_insurance (Fase 3) —
    // "tipo_atendimento_outro_descricao" fica de fora de propósito: "Outro"
    // é classificação administrativa da clínica, nunca exposta ao paciente
    // (decisão confirmada — ver plano da Fase 3).
    const CONVENIO_FIELDS = [
        'tipo_atendimento', 'convenio_id', 'convenio_numero_carteirinha',
        'convenio_titular', 'convenio_titular_cpf', 'convenio_titular_parentesco',
    ];

    public function __construct(private PatientInviteService $service) {}

    public function show(string $token)
    {
        $invite = $this->service->findByToken($token);
        $reason = $this->service->invalidReason($invite);

        if ($reason) {
            return Inertia::render('PatientInvites/Invalid', ['reason' => $reason]);
        }

        // Convite já concluído (reaberto numa segunda aba/dispositivo, ou
        // via F5 depois de terminar): mostra a mesma tela de conclusão de
        // novo, em vez do wizard editável — não há mais nada a preencher.
        $conclusion = $invite->status === 'concluido'
            ? $this->service->completionPayload($invite)
            : null;

        if (! $conclusion) {
            $this->service->markOpenedIfNeeded($invite);
            $invite->refresh();
        }

        return Inertia::render('PatientInvites/PublicWizard', [
            'token'      => $token,
            // Só current_step/allow_insurance são consumidos pelo wizard
            // hoje — kind/status não têm uso no frontend ainda (kind vai
            // importar quando a copy diferenciada "atualização cadastral"
            // for implementada, BRD §19 Fase 6; não adiantar isso agora).
            'invite'     => [
                'current_step'   => $invite->current_step,
                'allow_insurance' => $invite->allow_insurance,
            ],
            'patient'    => $invite->patient->only(array_merge(['nome', 'sobrenome'], $this->draftFields($invite))),
            // Mesma query de Convenio::active() já usada em outros lugares do
            // projeto (ex.: PatientController::create()) — sem ClinicScope
            // aqui (rota pública, sem sessão, BRD §3.2), filtrado manualmente
            // por clinic_id lido do próprio convite encontrado por token.
            'convenios'  => $invite->allow_insurance
                ? Convenio::active()->where('clinic_id', $invite->clinic_id)->orderBy('ordem')->orderBy('nome')->get(['id', 'nome'])
                : [],
            'conclusion' => $conclusion,
        ]);
    }

    public function update(Request $request, string $token)
    {
        $invite = $this->service->findByToken($token);

        // isActive() cobre os 3 status terminais de uma vez (concluido
        // incluso) — um convite já concluído não pode ser reescrito por uma
        // segunda aba/dispositivo que ainda esteja com o formulário aberto.
        if (! $invite || ! $invite->isActive()) {
            return response()->json(['message' => 'Este convite não está mais disponível.'], 410);
        }

        $rules = $this->draftValidationRules($invite);
        $rules['current_step'] = 'nullable|string|in:' . implode(',', $invite->wizardSteps());

        $validated = $request->validate($rules);
        $step = $validated['current_step'] ?? null;
        unset($validated['current_step']);

        $this->service->saveDraftFields($invite, $validated, $step);
        $invite->refresh();

        return response()->json([
            'invite' => [
                'status'       => $invite->status,
                'current_step' => $invite->current_step,
                'progress'     => $invite->progress,
            ],
        ]);
    }

    public function complete(string $token)
    {
        $invite = $this->service->findByToken($token);

        if (! $invite || ! $invite->isActive()) {
            return response()->json(['message' => 'Este convite não está mais disponível.'], 410);
        }

        return response()->json($this->service->complete($invite));
    }

    /**
     * Allowlist deste convite específico — os campos de convênio só entram
     * quando allow_insurance é verdadeiro (Fase 3), mesmo que o payload de
     * uma requisição forjada tente incluí-los: sem regra definida para eles
     * aqui, validate() nunca os devolve em $validated, então nunca chegam a
     * saveDraftFields(). A UI escondendo a etapa não é a única camada.
     */
    private function draftFields(PatientInvite $invite): array
    {
        return $invite->allow_insurance
            ? array_merge(self::DRAFT_FIELDS, self::CONVENIO_FIELDS)
            : self::DRAFT_FIELDS;
    }

    /**
     * Mesmas regras de PatientController::patientValidationRules() (única
     * fonte de verdade sobre o que cada campo aceita — tipo, tamanho,
     * `in:`), só com "required" removido: durante o preenchimento, a maior
     * parte dos campos está por definição incompleta a maior parte do tempo
     * (BRD §8.1). A validação completa só roda em complete()/PatientController.
     */
    private function draftValidationRules(PatientInvite $invite): array
    {
        $fullRules = (new PatientController())->patientValidationRules();

        return collect($fullRules)
            ->only($this->draftFields($invite))
            ->map(function (string $rule) {
                $parts = array_values(array_filter(explode('|', $rule), fn ($p) => $p !== 'required'));
                if (! in_array('nullable', $parts, true)) {
                    array_unshift($parts, 'nullable');
                }
                return implode('|', $parts);
            })
            ->toArray();
    }
}
