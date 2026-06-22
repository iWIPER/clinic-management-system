<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use App\Models\Invite;
use App\Models\Plan;
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
        ]);
    }

    public function storeClinic(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:odontologia,medicina,estetica,outros',
            'cnpj' => 'nullable|string|max:20',
            'plan_slug' => 'required|string|exists:plans,slug',
        ]);

        $user = Auth::user();

        DB::transaction(function () use ($validated, $user) {
            $plan = Plan::where('slug', $validated['plan_slug'])->firstOrFail();

            $clinic = Clinic::create([
                'name' => $validated['name'],
                'slug' => Str::slug($validated['name']) . '-' . Str::random(6),
                'type' => $validated['type'],
                'cnpj' => $validated['cnpj'],
                'plan_id' => $plan->id,
                'status' => 'trial',
            ]);

            // O criador vira owner
            $clinic->users()->attach($user->id, ['role' => 'owner']);

            // Definir como clínica atual
            session(['current_clinic_id' => $clinic->id]);
            session(['current_clinic' => $clinic->only('id', 'name', 'type')]);
        });

        return redirect()->route('onboarding.invite-team')
            ->with('success', 'Clínica criada com sucesso!');
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
