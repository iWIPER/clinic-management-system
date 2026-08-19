<?php

namespace App\Services\Admin;

use App\Models\AccessLog;
use App\Models\Clinic;
use App\Models\Plan;
use App\Models\ReferralConversion;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Fase System Admin/Backoffice — exportações administrativas em CSV,
 * sempre via streaming (Storage::disk nunca entra em jogo, tudo em
 * memória de execução mas nunca todo o dataset de uma vez — cada dataset
 * usa cursor()/chunk() do Eloquent, então mesmo com dezenas de milhares
 * de linhas o uso de memória fica plano).
 *
 * Deliberadamente SEM dataset de pacientes: seria um export bruto de dado
 * de saúde de todas as clínicas da plataforma de uma vez só — contra o
 * princípio de minimização de dados (ver docs/LGPD_ARQUITETURA.md). Cada
 * clínica já exporta os próprios pacientes pela tela normal dela.
 */
class ExportService
{
    public const DATASETS = [
        'clinics'      => 'Clínicas',
        'users'        => 'Usuários',
        'clinic_users' => 'Vínculos usuário-clínica',
        'subscriptions' => 'Assinaturas',
        'plans'        => 'Planos',
        'referrals'    => 'Indicações/conversões',
        'logs'         => 'Logs de auditoria',
    ];

    public function stream(string $dataset, array $filters = []): StreamedResponse
    {
        return match ($dataset) {
            'clinics'       => $this->streamClinics($filters),
            'users'         => $this->streamUsers($filters),
            'clinic_users'  => $this->streamClinicUsers(),
            'subscriptions' => $this->streamSubscriptions($filters),
            'plans'         => $this->streamPlans(),
            'referrals'     => $this->streamReferrals($filters),
            'logs'          => $this->streamLogs($filters),
            default         => abort(404, 'Dataset de exportação desconhecido.'),
        };
    }

    private function csvResponse(string $filename, array $header, callable $rows): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($header, $rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF"); // BOM — Excel PT-BR abre acentuação corretamente
            fputcsv($handle, $header, ';');

            $rows($handle);

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', "attachment; filename=\"{$filename}\"");

        return $response;
    }

    private function streamClinics(array $filters): StreamedResponse
    {
        return $this->csvResponse('clinicas.csv',
            ['ID', 'Nome', 'Nome Fantasia', 'Status', 'Plano', 'Assinatura', 'Membros', 'Pacientes', 'Criada em'],
            function ($handle) use ($filters) {
                Clinic::with(['subscription.plan'])
                    ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
                    ->withCount(['users', 'patients'])
                    ->orderBy('id')
                    ->cursor()
                    ->each(function (Clinic $c) use ($handle) {
                        fputcsv($handle, [
                            $c->id, $c->name, $c->trade_name, $c->status,
                            $c->subscription?->plan?->name ?? '', $c->subscription?->status ?? '',
                            $c->users_count, $c->patients_count,
                            $c->created_at->format('Y-m-d H:i:s'),
                        ], ';');
                    });
            }
        );
    }

    private function streamUsers(array $filters): StreamedResponse
    {
        return $this->csvResponse('usuarios.csv',
            ['ID', 'Nome', 'E-mail', 'Status', 'Clínicas vinculadas', 'System Admin', 'Último login', 'Criado em'],
            function ($handle) use ($filters) {
                User::query()
                    ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
                    ->withCount('clinics')
                    ->orderBy('id')
                    ->cursor()
                    ->each(function (User $u) use ($handle) {
                        fputcsv($handle, [
                            $u->id, $u->name, $u->email, $u->status ?? 'ativo',
                            $u->clinics_count, $u->isSystemAdmin() ? 'sim' : 'não',
                            $u->last_login_at?->format('Y-m-d H:i:s') ?? '',
                            $u->created_at->format('Y-m-d H:i:s'),
                        ], ';');
                    });
            }
        );
    }

    private function streamClinicUsers(): StreamedResponse
    {
        return $this->csvResponse('vinculos-usuario-clinica.csv',
            ['Usuário ID', 'Usuário', 'Clínica ID', 'Clínica', 'Cargo', 'Vinculado em'],
            function ($handle) {
                DB::table('clinic_user')
                    ->join('users', 'users.id', '=', 'clinic_user.user_id')
                    ->join('clinics', 'clinics.id', '=', 'clinic_user.clinic_id')
                    ->select('users.id as user_id', 'users.name as user_name', 'clinics.id as clinic_id', 'clinics.name as clinic_name', 'clinic_user.role', 'clinic_user.created_at')
                    ->orderBy('clinic_user.id')
                    ->cursor()
                    ->each(function ($row) use ($handle) {
                        fputcsv($handle, [
                            $row->user_id, $row->user_name, $row->clinic_id, $row->clinic_name,
                            $row->role, $row->created_at,
                        ], ';');
                    });
            }
        );
    }

    private function streamSubscriptions(array $filters): StreamedResponse
    {
        return $this->csvResponse('assinaturas.csv',
            ['ID', 'Clínica', 'Plano', 'Status', 'Intervalo', 'Trial até', 'Próxima cobrança'],
            function ($handle) use ($filters) {
                Subscription::with(['clinic:id,name', 'plan:id,name'])
                    ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
                    ->orderBy('id')
                    ->cursor()
                    ->each(function (Subscription $s) use ($handle) {
                        fputcsv($handle, [
                            $s->id, $s->clinic?->name ?? '', $s->plan?->name ?? '', $s->status, $s->interval,
                            $s->trial_ends_at?->format('Y-m-d') ?? '', $s->next_billing_at?->format('Y-m-d') ?? '',
                        ], ';');
                    });
            }
        );
    }

    private function streamPlans(): StreamedResponse
    {
        return $this->csvResponse('planos.csv',
            ['ID', 'Nome', 'Preço mensal', 'Preço anual', 'Trial (dias)', 'Máx. pacientes', 'Máx. usuários', 'Ativo', 'Assinaturas'],
            function ($handle) {
                Plan::withCount('subscriptions')->orderBy('sort_order')->get()->each(function (Plan $p) use ($handle) {
                    fputcsv($handle, [
                        $p->id, $p->name, $p->price_monthly, $p->price_yearly, $p->trial_days,
                        $p->max_patients, $p->max_users, $p->is_active ? 'sim' : 'não', $p->subscriptions_count,
                    ], ';');
                });
            }
        );
    }

    private function streamReferrals(array $filters): StreamedResponse
    {
        return $this->csvResponse('indicacoes.csv',
            ['ID', 'Indicador', 'Indicado', 'Plano', 'Recompensa', 'Status', 'Elegível em', 'Pago em'],
            function ($handle) use ($filters) {
                ReferralConversion::with(['referral.clinic:id,name', 'referredClinic:id,name', 'plan:id,name'])
                    ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
                    ->orderBy('id')
                    ->cursor()
                    ->each(function (ReferralConversion $c) use ($handle) {
                        fputcsv($handle, [
                            $c->id, $c->referral?->clinic?->name ?? '', $c->referredClinic?->name ?? '',
                            $c->plan?->name ?? '', $c->reward_amount, $c->status,
                            $c->eligible_at?->format('Y-m-d') ?? '', $c->paid_at?->format('Y-m-d') ?? '',
                        ], ';');
                    });
            }
        );
    }

    private function streamLogs(array $filters): StreamedResponse
    {
        return $this->csvResponse('logs-auditoria.csv',
            ['ID', 'Ação', 'Descrição', 'Usuário', 'Clínica', 'IP', 'Data/hora'],
            function ($handle) use ($filters) {
                AccessLog::with(['user:id,name', 'clinic:id,name'])
                    ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', $d))
                    ->when($filters['to'] ?? null, fn ($q, $d) => $q->where('created_at', '<=', $d))
                    ->when($filters['action'] ?? null, fn ($q, $a) => $q->where('action', $a))
                    ->when($filters['clinic_id'] ?? null, fn ($q, $c) => $q->where('clinic_id', $c))
                    ->orderBy('id')
                    ->cursor()
                    ->each(function (AccessLog $log) use ($handle) {
                        fputcsv($handle, [
                            $log->id, $log->action, $log->description,
                            $log->user?->name ?? '', $log->clinic?->name ?? '',
                            $log->ip_address, $log->created_at->format('Y-m-d H:i:s'),
                        ], ';');
                    });
            }
        );
    }
}
