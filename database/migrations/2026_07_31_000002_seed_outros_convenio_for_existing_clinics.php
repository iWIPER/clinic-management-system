<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A opção "Particular / não informado" saiu do formulário de tratamento
     * (ver TreatmentFormModal.vue) — toda clínica passa a precisar também de
     * um convênio "Outros" real no cadastro, como catch-all. Novas clínicas
     * recebem isso no onboarding (OnboardingController); esta migration cobre
     * as que já existiam antes dessa mudança.
     */
    public function up(): void
    {
        $now = now();

        DB::table('clinics')->select('id')->orderBy('id')->chunkById(200, function ($clinics) use ($now) {
            foreach ($clinics as $clinic) {
                $exists = DB::table('convenios')
                    ->where('clinic_id', $clinic->id)
                    ->where('nome', 'Outros')
                    ->exists();

                if (! $exists) {
                    DB::table('convenios')->insert([
                        'clinic_id'  => $clinic->id,
                        'nome'       => 'Outros',
                        'ativo'      => true,
                        'ordem'      => 999,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        DB::table('convenios')->where('nome', 'Outros')->where('ordem', 999)->delete();
    }
};
