<?php

namespace App\Http\Controllers;

use App\Models\PatientInvite;
use App\Services\PatientInviteService;
use Illuminate\Http\Request;

class PatientInviteController extends Controller
{
    public function __construct(private PatientInviteService $service) {}

    public function checkPhone(Request $request)
    {
        $validated = $request->validate([
            'telefone' => 'required|string|max:30',
        ]);

        $patient = $this->service->findPatientByPhone($validated['telefone'], session('current_clinic_id'));

        $activeInvite = $patient
            ? $this->service->findActiveInvite($patient->id, 'atualizacao')
            : null;

        return response()->json([
            'patient'       => $patient?->only(['id', 'nome', 'sobrenome']),
            'active_invite' => $activeInvite ? [
                'id'         => $activeInvite->id,
                'kind'       => $activeInvite->kind,
                'created_at' => $activeInvite->created_at->toISOString(),
            ] : null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nome'                  => 'required|string|max:100',
            'sobrenome'             => 'required|string|max:100',
            'telefone'              => 'required|string|max:30',
            'email'                 => 'nullable|email|max:255',
            'existing_patient_id'   => 'nullable|exists:patients,id',
            'kind'                  => 'required|string|in:cadastro,atualizacao',
            'allow_insurance'       => 'boolean',
            'allow_anamnesis'       => 'boolean',
            // Sem exists:anamnesis_templates,id de propósito: essa tabela
            // pertence ao módulo de Anamnese, commitado separadamente (ver
            // comentário equivalente na migration de patient_invites).
            'anamnesis_template_id' => 'nullable|integer|required_if:allow_anamnesis,true',
            'channel'               => 'required|string|in:whatsapp,email,link_only',
            'expires_in_days'       => 'nullable|integer|in:7,15,30',
        ]);

        if ($validated['channel'] === 'email' && empty($validated['email'])) {
            return response()->json([
                'message' => 'Informe um e-mail do paciente para enviar por este canal.',
                'errors'  => ['email' => ['Obrigatório quando o canal de envio é e-mail.']],
            ], 422);
        }

        $invite = $this->service->create($validated, session('current_clinic_id'), $request->user()->id);

        return response()->json([
            'invite' => $invite->only(['id', 'kind', 'status', 'channel']),
            'share'  => $this->service->buildShareData($invite),
        ]);
    }

    public function qrcode(PatientInvite $invite)
    {
        $svg = $this->service->generateQrCodeSvg($this->service->buildInviteUrl($invite));

        return response($svg, 200, ['Content-Type' => 'image/svg+xml']);
    }

    public function resend(PatientInvite $invite, Request $request)
    {
        return response()->json(['share' => $this->service->resend($invite, $request->user()->id)]);
    }

    public function cancel(PatientInvite $invite, Request $request)
    {
        $this->service->cancel($invite, cancelledById: $request->user()->id);

        return response()->json(['ok' => true]);
    }

    public function regenerate(PatientInvite $invite, Request $request)
    {
        $new = $this->service->regenerate($invite, $request->user()->id);

        return response()->json([
            'invite' => $new->only(['id', 'kind', 'status', 'channel']),
            'share'  => $this->service->buildShareData($new),
        ]);
    }

    public function logEvent(PatientInvite $invite, Request $request)
    {
        $validated = $request->validate([
            'action' => 'required|string|in:link_copied,whatsapp_link_generated',
        ]);

        $this->service->logFrontendEvent($invite, $validated['action'], $request->user()->id);

        return response()->json(['ok' => true]);
    }
}
