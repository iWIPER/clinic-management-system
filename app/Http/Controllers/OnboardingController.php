<?php

namespace App\Http\Controllers;

use App\Models\Chair;
use App\Models\Clinic;
use App\Models\Convenio;
use App\Models\Invite;
use App\Models\Plan;
use App\Models\Referral;
use App\Services\ReferralService;
use App\Services\SubscriptionService;
use App\Services\WildentalCatalogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class OnboardingController extends Controller
{
    public function showRoleChoice()
    {
        return Inertia::render('Onboarding/RoleChoice');
    }

    /**
     * Usuário escolheu ser proprietário ou convidado.
     */
    public function chooseRole(Request $request)
    {
        $validated = $request->validate([
            'role_type' => 'required|in:owner,guest',
        ]);

        if ($validated['role_type'] === 'owner') {
            return redirect()->route('onboarding.create-clinic');
        }

        // Convidado: mostrar tela para aceitar convite ou inserir token/email
        return redirect()->route('onboarding.join-invite');
    }

    /**
     * Formulário para criar a primeira clínica (owner).
     */
    public function createClinic()
    {
        $plans = Plan::orderBy('price_monthly_cents')->get(['id', 'name', 'slug', 'is_free', 'price_monthly_cents']);

        return Inertia::render('Onboarding/CreateClinic', [
            'plans' => $plans,
            'maxChairs' => Chair::MAX_PER_CLINIC,
        ]);
    }

    public function storeClinic(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:odontologia,medicina,estetica,outros',
            'cnpj' => 'nullable|string|max:20',
            'plan_slug' => 'required|string|exists:plans,slug',
            'onboarding_stage' => 'required|string|in:new,under_1y,1_to_5y,over_5y',
            'onboarding_current_system' => 'required|string|in:paper_or_calendar,spreadsheet,other_system',
            'chairs_count' => 'required|integer|min:1|max:' . Chair::MAX_PER_CLINIC,
        ]);

        $user = Auth::user();

        DB::transaction(function () use ($validated, $user, $request) {
            $plan = Plan::where('slug', $validated['plan_slug'])->firstOrFail();

            $clinic = Clinic::create([
                'name' => $validated['name'],
                'slug' => Str::slug($validated['name']) . '-' . Str::random(6),
                'type' => $validated['type'],
                'onboarding_stage' => $validated['onboarding_stage'],
                'onboarding_current_system' => $validated['onboarding_current_system'],
                'cnpj' => $validated['cnpj'],
                'plan_id' => $plan->id,
                'status' => 'trial',
            ]);

            // O criador vira owner
            $clinic->users()->attach($user->id, ['role' => 'owner']);

            // Assinatura trial — sessão tem prioridade; cookie cobre o caso de a
            // sessão ter expirado entre o clique no link e a conclusão do cadastro.
            $referralCode = session('referral_code') ?? $request->cookie('referral_code');
            app(SubscriptionService::class)->startTrial($clinic, $plan, (bool) $referralCode);

            // Programa de indicações — link permanente para a nova clínica
            $referralService = app(ReferralService::class);
            $referralService->getOrCreate($clinic);
            $referralService->getOrCreateWallet($clinic);

            // Conversão via link de indicação
            if ($referralCode) {
                $referrer = Referral::where('code', $referralCode)->where('is_active', true)->first();
                if ($referrer && $referrer->clinic_id !== $clinic->id) {
                    $referralService->registerConversion($referrer, $clinic, $request);

                    \App\Models\AccessLog::record(
                        action: 'referral_trial_started',
                        description: 'Uma nova clínica iniciou o período de teste utilizando seu link.',
                        metadata: ['referred_clinic_id' => $clinic->id],
                        clinicId: $referrer->clinic_id,
                    );
                }
                session()->forget('referral_code');
                \Illuminate\Support\Facades\Cookie::queue(\Illuminate\Support\Facades\Cookie::forget('referral_code'));
            }

            // Definir como clínica atual
            session(['current_clinic_id' => $clinic->id]);
            session(['current_clinic' => $clinic->toSessionPayload()]);

            app(WildentalCatalogService::class)->seedForClinic($clinic, $user->id);

            Chair::seedDefaultsForClinic($clinic->id, (int) $validated['chairs_count']);

            Convenio::create([
                'clinic_id' => $clinic->id,
                'nome'      => 'Particular',
                'ativo'     => true,
                'ordem'     => 0,
            ]);

            Convenio::create([
                'clinic_id' => $clinic->id,
                'nome'      => 'Outros',
                'ativo'     => true,
                'ordem'     => 999,
            ]);
        });

        return redirect()->route('onboarding.complete')
            ->with('success', 'Clínica criada com sucesso!');
    }

    /**
     * Tela final do onboarding — resumo do que foi configurado.
     */
    public function complete()
    {
        $clinic = Clinic::findOrFail(session('current_clinic_id'));

        return Inertia::render('Onboarding/Complete', [
            'clinicName' => $clinic->name,
            'chairsCount' => Chair::where('clinic_id', $clinic->id)->count(),
        ]);
    }

    /**
     * Tela para convidar a equipe (após criar clínica).
     */
    public function inviteTeam()
    {
        $clinic = Auth::user()->currentClinic();

        return Inertia::render('Onboarding/InviteTeam', [
            'clinic' => $clinic,
        ]);
    }

    public function sendInvites(Request $request)
    {
        $validated = $request->validate([
            'invites' => 'required|array|min:1',
            'invites.*.email' => 'required|email',
            'invites.*.role' => 'required|in:admin,professional,staff',
        ]);

        $user = Auth::user();
        $clinicId = session('current_clinic_id');

        foreach ($validated['invites'] as $inviteData) {
            // Evitar duplicados
            Invite::updateOrCreate(
                ['clinic_id' => $clinicId, 'email' => $inviteData['email']],
                [
                    'role' => $inviteData['role'],
                    'invited_by_id' => $user->id,
                    'token' => Str::random(32),
                    'expires_at' => now()->addDays(7),
                ]
            );

            // TODO: Enviar email real (Mail)
            // Mail::to($inviteData['email'])->send(new ClinicInviteMail(...));
        }

        return redirect()->route('dashboard')
            ->with('success', 'Convites enviados com sucesso!');
    }

    public function joinInvite()
    {
        return Inertia::render('Onboarding/JoinClinic');
    }

    public function acceptInvite(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string|exists:invites,token',
        ]);

        $invite = Invite::where('token', $validated['token'])->first();

        if ($invite->isExpired()) {
            return back()->withErrors(['token' => 'Este convite expirou.']);
        }

        $user = Auth::user();

        // Verifica se o email bate (opcional mas recomendado)
        if (strtolower($user->email) !== strtolower($invite->email)) {
            return back()->withErrors(['token' => 'Este convite foi enviado para outro email.']);
        }

        $invite->accept($user);

        return redirect()->route('dashboard')
            ->with('success', 'Você entrou na clínica com sucesso!');
    }
}
