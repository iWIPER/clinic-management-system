<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            if (! Schema::hasColumn('plans', 'description')) {
                $table->text('description')->nullable()->after('slug');
            }
            if (! Schema::hasColumn('plans', 'price_monthly')) {
                $table->decimal('price_monthly', 10, 2)->default(0)->after('price_yearly_cents');
            }
            if (! Schema::hasColumn('plans', 'price_yearly')) {
                $table->decimal('price_yearly', 10, 2)->default(0)->after('price_monthly');
            }
            if (! Schema::hasColumn('plans', 'trial_days')) {
                $table->unsignedInteger('trial_days')->default(7)->after('price_yearly');
            }
            if (! Schema::hasColumn('plans', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('is_free');
            }
            if (! Schema::hasColumn('plans', 'is_featured')) {
                $table->boolean('is_featured')->default(false)->after('is_active');
            }
            if (! Schema::hasColumn('plans', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('is_featured');
            }
        });

        // Sincroniza preços decimais a partir dos centavos existentes
        foreach (DB::table('plans')->orderBy('id')->get() as $plan) {
            DB::table('plans')->where('id', $plan->id)->update([
                'price_monthly' => ($plan->price_monthly_cents ?? 0) / 100,
                'price_yearly'  => ($plan->price_yearly_cents ?? 0) / 100,
            ]);
        }

        if (! Schema::hasTable('plan_features')) {
            Schema::create('plan_features', function (Blueprint $table) {
                $table->id();
                $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
                $table->string('feature_key');
                $table->string('feature_label');
                $table->string('feature_value')->nullable();
                $table->boolean('included')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        $this->seedPlansAndFeatures();
    }

    private function seedPlansAndFeatures(): void
    {
        $planMap = [
            'starter' => [
                'name'        => 'Starter',
                'description' => 'Ideal para clínicas que estão começando',
                'trial_days'  => 7,
                'sort_order'  => 1,
                'features'    => [
                    ['agenda', 'Agenda completa', null],
                    ['patients', 'Até 500 pacientes', '500'],
                    ['users', 'Até 5 usuários', '5'],
                    ['finance_basic', 'Financeiro básico', null],
                    ['drive', 'Google Drive', null],
                ],
            ],
            'pro' => [
                'name'        => 'Professional',
                'description' => 'Para clínicas em crescimento',
                'trial_days'  => 7,
                'is_featured' => true,
                'sort_order'  => 2,
                'features'    => [
                    ['agenda', 'Agenda completa', null],
                    ['patients', 'Pacientes ilimitados', null],
                    ['users', 'Até 15 usuários', '15'],
                    ['finance_basic', 'Financeiro básico', null],
                    ['finance_advanced', 'Financeiro avançado', null],
                    ['inventory', 'Estoque', null],
                    ['drive', 'Google Drive ilimitado', null],
                    ['integrations', 'Integrações', null],
                    ['reports', 'Relatórios', null],
                    ['referral', 'Programa de indicação', null],
                ],
            ],
            'premium' => [
                'name'        => 'Enterprise',
                'description' => 'Para grandes clínicas e redes',
                'trial_days'  => 14,
                'sort_order'  => 3,
                'features'    => [
                    ['all_features', 'Todos os recursos', null],
                    ['api', 'API', null],
                    ['marketplace', 'Marketplace financeiro', null],
                    ['multi_unit', 'Multiunidades', null],
                    ['advanced_permissions', 'Permissões avançadas', null],
                    ['audit', 'Auditoria', null],
                    ['premium_integrations', 'Integrações premium', null],
                    ['priority_support', 'Suporte prioritário', null],
                ],
            ],
        ];

        foreach ($planMap as $slug => $data) {
            $plan = DB::table('plans')->where('slug', $slug)->first();
            if (! $plan) {
                continue;
            }

            DB::table('plans')->where('id', $plan->id)->update([
                'name'        => $data['name'],
                'description' => $data['description'],
                'trial_days'  => $data['trial_days'],
                'is_featured' => $data['is_featured'] ?? false,
                'sort_order'  => $data['sort_order'],
                'is_active'   => true,
                'updated_at'  => now(),
            ]);

            if (DB::table('plan_features')->where('plan_id', $plan->id)->exists()) {
                continue;
            }

            $features = [];
            $i = 1;
            foreach ($data['features'] as [$key, $label, $value]) {
                $features[] = [
                    'plan_id'       => $plan->id,
                    'feature_key'   => $key,
                    'feature_label' => $label,
                    'feature_value' => $value,
                    'included'      => true,
                    'sort_order'    => $i++,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ];
            }
            DB::table('plan_features')->insert($features);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_features');

        Schema::table('plans', function (Blueprint $table) {
            $cols = ['description', 'price_monthly', 'price_yearly', 'trial_days', 'is_active', 'is_featured', 'sort_order'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('plans', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};