<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Toda clínica precisa de ao menos o convênio "Particular" para o módulo
     * de Tratamentos e o cadastro de paciente funcionarem. Novas clínicas
     * recebem isso no onboarding (OnboardingController); esta migration cobre
     * as que já existiam antes do módulo.
     */
    public function up(): void
    {
        $now = now();

        DB::table('clinics')->select('id')->orderBy('id')->chunkById(200, function ($clinics) use ($now) {
            foreach ($clinics as $clinic) {
                $exists = DB::table('convenios')
                    ->where('clinic_id', $clinic->id)
                    ->where('nome', 'Particular')
                    ->exists();

                if (! $exists) {
                    DB::table('convenios')->insert([
                        'clinic_id'  => $clinic->id,
                        'nome'       => 'Particular',
                        'ativo'      => true,
                        'ordem'      => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        DB::table('convenios')->where('nome', 'Particular')->delete();
    }
};
