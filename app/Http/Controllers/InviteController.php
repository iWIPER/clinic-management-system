<?php

namespace App\Http\Controllers;

use App\Models\AccessLog;
use App\Models\Invite;
use App\Models\User;
use App\Services\InviteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;

class InviteController extends Controller
{
    public function __construct(private readonly InviteService $service) {}

    // ── 1. Verificar cenário antes do INSERT (sem side-effects) ───────────
    public function check(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $request->validate(['email' => 'required|email']);

        $result = $this->service->checkScenario(
            $request->email,
            (int) session('current_clinic_id')
        );

        return response()->json($result);
    }

    // ── 2. Criar convite (upsert, respeita UNIQUE constraint) ─────────────
    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $clinicId  = (int) session('current_clinic_id');
        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|max:255',
            'job_title' => 'required|string|in:' . implode(',', Invite::JOB_TITLES),
        ]);

        // Salvaguarda final: nunca inserir se já é membro
        $check = $this->service->checkScenario($validated['email'], $clinicId);
        if ($check['scenario'] === InviteService::SCENARIO_MEMBER) {
            return response()->json([
                'error'   => 'already_member',
                'message' => 'Este usuário já é membro desta clínica.',
            ], 422);
        }

        $invite = $this->service->createOrUpdate($validated, $clinicId, Auth::id());

        AccessLog::record(
            action: AccessLog::ACTION_INVITE_SENT,
            description: "Convite criado para {$invite->name} ({$invite->email}) — código: {$invite->short_token}",
            metadata: ['invite_id' => $invite->id, 'short_token' => $invite->short_token, 'email' => $invite->email],
        );

        $emailResult = $this->service->dispatchEmail($invite);

        return response()->json([
            'invite'       => InviteService::formatInvite($invite),
            'email_result' => $emailResult,
            'invite_link'  => config('app.url') . '/convites/' . $invite->token,
        ]);
    }

    // ── 3. Reenviar e-mail (renova credencial e validade) ──────────────────
    public function resend(Invite $invite): JsonResponse
    {
        $this->authorizeAdmin();
        abort_unless($invite->clinic_id == session('current_clinic_id'), 403);
        abort_if($invite->status !== 'pending' || $invite->isExpired(), 422);

        // Reenvio renova o token, não só a validade — evita que um link já
        // circulado (ex.: e-mail encaminhado por engano) continue válido
        // depois de um reenvio "de boa fé". Mesmo mecanismo de
        // regenerateToken(), só o texto de log/auditoria muda.
        $invite = $this->service->regenerateToken($invite);

        Log::info('[InviteController] Reenvio de convite solicitado', [
            'invite_id'   => $invite->id,
            'short_token' => $invite->short_token,
            'email'       => $invite->email,
        ]);

        AccessLog::record(
            action: AccessLog::ACTION_INVITE_RESENT,
            description: "Convite reenviado para {$invite->email}",
            metadata: ['invite_id' => $invite->id, 'short_token' => $invite->short_token],
        );

        $emailResult = $this->service->dispatchEmail($invite);

        return response()->json([
            'invite'       => InviteService::formatInvite($invite),
            'email_result' => $emailResult,
        ]);
    }

    // ── 4. Gerar novo código (novos tokens, mantém e-mail e dados) ─────────
    public function regenerateToken(Invite $invite): JsonResponse
    {
        $this->authorizeAdmin();
        abort_unless($invite->clinic_id == session('current_clinic_id'), 403);

        $invite = $this->service->regenerateToken($invite);

        AccessLog::record(
            action: 'invite_token_regenerated',
            description: "Novo código gerado para convite de {$invite->email} — novo código: {$invite->short_token}",
            metadata: ['invite_id' => $invite->id, 'new_short_token' => $invite->short_token],
        );

        $emailResult = $this->service->dispatchEmail($invite);

        return response()->json([
            'invite'       => InviteService::formatInvite($invite),
            'email_result' => $emailResult,
        ]);
    }

    // ── 5. Reativar convite expirado ───────────────────────────────────────
    public function reactivate(Invite $invite): JsonResponse
    {
        $this->authorizeAdmin();
        abort_unless($invite->clinic_id == session('current_clinic_id'), 403);

        $invite = $this->service->reactivate($invite);

        AccessLog::record(
            action: 'invite_reactivated',
            description: "Convite expirado reativado para {$invite->email}",
            metadata: ['invite_id' => $invite->id, 'new_short_token' => $invite->short_token],
        );

        $emailResult = $this->service->dispatchEmail($invite);

        return response()->json([
            'invite'       => InviteService::formatInvite($invite),
            'email_result' => $emailResult,
        ]);
    }

    // ── 6. Cancelar convite ────────────────────────────────────────────────
    public function destroy(Invite $invite): JsonResponse
    {
        $this->authorizeAdmin();
        abort_unless($invite->clinic_id == session('current_clinic_id'), 403);

        $this->service->cancel($invite);

        AccessLog::record(
            action: AccessLog::ACTION_INVITE_CANCELLED,
            description: "Convite para {$invite->email} cancelado pelo administrador",
            metadata: ['invite_id' => $invite->id],
        );

        return response()->json(['ok' => true]);
    }

    // ── 7. Página pública de aceite ────────────────────────────────────────
    public function show(string $token): \Inertia\Response
    {
        // O short_token (formato AAA-999) é só um código de referência
        // visual — a credencial real de acesso é sempre o token forte
        // (Str::random(32)), o único aceito aqui e em accept().
        $invite = Invite::where('token', $token)
            ->with(['clinic:id,name,trade_name,logo_path,logo_type,default_logo', 'invitedBy:id,name'])
            ->firstOrFail();

        if ($invite->isExpired() || $invite->status !== 'pending') {
            return Inertia::render('Invites/Invalid', [
                'reason' => $invite->isExpired() ? 'expired' : 'used',
            ]);
        }

        $existingUser   = User::where('email', $invite->email)->first();
        $isLoggedInUser = Auth::check() && Auth::user()->email === $invite->email;

        return Inertia::render('Invites/Accept', [
            'invite' => [
                'id'          => $invite->id,
                'type'        => $invite->type,
                'token'       => $token,
                'name'        => $invite->name,
                'email'       => $invite->email,
                'job_title'   => $invite->job_title,
                'short_token' => $invite->short_token,
                'expires_at'  => $invite->expires_at,
                'days_left'   => max(0, (int) now()->diffInDays($invite->expires_at, false)),
                'invited_by'  => $invite->invitedBy?->name,
            ],
            'clinic' => [
                'name'     => $invite->clinic?->trade_name ?? $invite->clinic?->name,
                'logo_url' => $invite->clinic ? $invite->clinic->logoUrl() : null,
            ],
            'userExists'    => $existingUser !== null,
            'isLoggedIn'    => Auth::check(),
            'isCorrectUser' => $isLoggedInUser,
        ]);
    }

    // ── 8. Processar aceite ────────────────────────────────────────────────
    public function accept(Request $request, string $token): \Illuminate\Http\RedirectResponse
    {
        // O short_token (formato AAA-999) é só um código de referência
        // visual — a credencial real de aceite é sempre o token forte
        // (Str::random(32)), o único aceito aqui e em show().
        $invite = Invite::where('token', $token)
            ->with('clinic')
            ->firstOrFail();

        abort_if($invite->isExpired() || $invite->status !== 'pending', 410, 'Convite inválido ou expirado.');

        $userExists = User::where('email', $invite->email)->exists();

        if ($userExists) {
            // Usuário já logado com o e-mail correto → apenas associar
            if (Auth::check() && Auth::user()->email === $invite->email) {
                $user = Auth::user();
            } else {
                // Verificar senha para confirmar identidade — mesmo padrão
                // de rate limit do LoginRequest, pra não virar oráculo de
                // senha sem limite de tentativas.
                $request->validate(
                    ['password' => 'required|string'],
                    ['password.required' => 'Informe sua senha para confirmar o aceite.']
                );

                $throttleKey = 'invite-accept:' . $invite->id . '|' . $request->ip();

                if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
                    $seconds = RateLimiter::availableIn($throttleKey);

                    return back()->withErrors(['password' => trans('auth.throttle', [
                        'seconds' => $seconds,
                        'minutes' => ceil($seconds / 60),
                    ])]);
                }

                $user = User::where('email', $invite->email)->firstOrFail();

                if (! Hash::check($request->password, $user->password)) {
                    RateLimiter::hit($throttleKey);

                    return back()->withErrors(['password' => 'Senha incorreta. Verifique e tente novamente.']);
                }

                RateLimiter::clear($throttleKey);
            }
        } else {
            // Novo usuário — criar conta
            $request->validate([
                'password'              => 'required|string|min:8|confirmed',
                'password_confirmation' => 'required|string',
            ], [
                'password.required'  => 'Defina uma senha para sua conta.',
                'password.min'       => 'A senha deve ter pelo menos 8 caracteres.',
                'password.confirmed' => 'As senhas não conferem.',
            ]);

            $user = User::create([
                'name'      => $invite->name ?? explode('@', $invite->email)[0],
                'email'     => $invite->email,
                'password'  => Hash::make($request->password),
                'job_title' => $invite->job_title,
            ]);

            Log::info('[InviteController] Novo usuário criado via aceite de convite', [
                'user_id'   => $user->id,
                'email'     => $user->email,
                'invite_id' => $invite->id,
            ]);
        }

        $invite->accept($user);

        AccessLog::record(
            action: AccessLog::ACTION_INVITE_ACCEPTED,
            description: "{$user->name} ingressou na clínica via convite",
            metadata: ['invite_id' => $invite->id, 'user_created' => ! $userExists],
            userId: $user->id,
            clinicId: $invite->clinic_id,
        );

        Log::info('[InviteController] Convite aceito com sucesso', [
            'invite_id'   => $invite->id,
            'user_id'     => $user->id,
            'clinic_id'   => $invite->clinic_id,
            'user_created'=> ! $userExists,
        ]);

        Auth::login($user);

        if ($invite->isAffiliateInvite()) {
            return redirect()->route('affiliate.dashboard')
                ->with('success', 'Bem-vindo(a) ao programa de afiliados Wildental!');
        }

        $clinicName = $invite->clinic?->trade_name ?? $invite->clinic?->name ?? 'a clínica';

        return redirect()->route('dashboard')
            ->with('success', "Bem-vindo(a) ao Wildental! Você agora faz parte da equipe de {$clinicName}.");
    }

    // ── Autorização ────────────────────────────────────────────────────────
    private function authorizeAdmin(): void
    {
        abort_unless(
            in_array(Auth::user()?->roleInCurrentClinic(), ['owner', 'admin']),
            403,
            'Acesso não autorizado.'
        );
    }
}
