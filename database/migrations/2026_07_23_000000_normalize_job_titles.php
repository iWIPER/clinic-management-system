<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Padroniza job_title para a lista única (Dentista, Secretário(a),
     * Administrador, Outro), já que o campo deixa de aceitar texto livre.
     */
    public function up(): void
    {
        $valid = ['Dentista', 'Secretário(a)', 'Administrador', 'Outro'];

        DB::table('users')->where('job_title', 'Secretária')->update(['job_title' => 'Secretário(a)']);
        DB::table('invites')->where('job_title', 'Secretária')->update(['job_title' => 'Secretário(a)']);

        DB::table('users')->whereNotNull('job_title')->whereNotIn('job_title', $valid)->update(['job_title' => 'Outro']);
        DB::table('invites')->whereNotNull('job_title')->whereNotIn('job_title', $valid)->update(['job_title' => 'Outro']);
    }

    public function down(): void
    {
        // Não reversível com segurança — os valores livres originais não são preservados.
    }
};
