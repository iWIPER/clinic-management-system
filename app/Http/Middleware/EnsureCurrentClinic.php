<?php

namespace App\Http\Middleware;

use App\Models\Clinic;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCurrentClinic
{
    /**
     * Garante que há uma clínica ativa no contexto da sessão.
     *
     * $mode 'strict' (default) bloqueia a requisição quando o usuário não
     * tem nenhuma clínica válida — usado em todo o contexto clínico da
     * aplicação. $mode 'onboarding' deixa a requisição prosseguir sem
     * clínica (o próprio fluxo de onboarding é como a primeira clínica é
     * criada), mantendo apenas o desvio de contas Affiliate e a suspensão.
     */
    public function handle(Request $request, Closure $next, string $mode = 'strict'): Response
    {
        $user = Auth::user();

        if (!$user) {
            return $next($request);
        }

        // Contas Affiliate não têm clínica — nunca devem ver telas clínicas.
        if ($user->isAffiliate()) {
            return redirect()->route('affiliate.dashboard');
        }

        // System Admin nunca entra automaticamente no contexto de clínica,
        // mesmo tendo vínculo real com uma — só mediante ação explícita
        // ("Entrar na clínica" no Backoffice, ver
        // Admin\ClinicController::enter()). Sem o flag admin_clinic_context
        // na sessão, qualquer rota clínica (inclusive onboarding — System
        // Admin nunca deveria ver as perguntas de configuração inicial)
        // volta pro Backoffice em vez de misturar os dois contextos.
        if ($user->isSystemAdmin() && !session('admin_clinic_context')) {
            return redirect()->route('admin.index');
        }

        $clinicId = session('current_clinic_id');

        // Nunca confiar cegamente no valor da sessão — reconfirma contra o
        // vínculo real em clinic_user antes de aceitar como válido (uma
        // sessão antiga/manipulada pode apontar pra uma clínica da qual o
        // usuário já foi removido, ou à qual nunca pertenceu).
        if ($clinicId && !$user->clinics()->where('clinics.id', $clinicId)->exists()) {
            session()->forget(['current_clinic_id', 'current_clinic']);
            $clinicId = null;
        }

        // Auto-pick nunca roda pra System Admin, mesmo em contexto de
        // clínica explicitamente aberto (admin_clinic_context) — a única
        // forma de um System Admin ganhar current_clinic_id é a ação
        // explícita em Admin\ClinicController::enter(), nunca um fallback
        // automático aqui.
        if (!$clinicId && !$user->isSystemAdmin()) {
            // Tenta pegar a primeira clínica do usuário
            $firstClinic = $user->clinics()->first();
            if ($firstClinic) {
                session([
                    'current_clinic_id' => $firstClinic->id,
                    'current_clinic'    => $firstClinic->toSessionPayload(),
                ]);
                $clinicId = $firstClinic->id;
            }
        }

        // Achado da fase System Admin/Backoffice: Admin\ClinicController::block()
        // já marcava a clínica como 'suspended' há tempos, mas nada checava
        // esse status em runtime — a clínica continuava 100% acessível pros
        // seus membros. Verificado a cada request (não só no login) porque a
        // suspensão pode acontecer com a sessão já aberta.
        if ($clinicId && ($clinic = Clinic::find($clinicId)) && $clinic->status === 'suspended') {
            session()->forget(['current_clinic_id', 'current_clinic']);

            abort(403, 'Esta clínica está temporariamente suspensa. Entre em contato com o suporte.');
        }

        // Fail-closed: sem clínica válida, o contexto clínico não pode ser
        // acessado — só o próprio onboarding (mode 'onboarding') tolera
        // esse estado, porque é ele quem cria a primeira clínica.
        if (!$clinicId && $mode === 'strict') {
            return redirect()->route('onboarding.choose-role');
        }

        // Aqui podemos adicionar verificação de entitlements depois

        return $next($request);
    }
}
