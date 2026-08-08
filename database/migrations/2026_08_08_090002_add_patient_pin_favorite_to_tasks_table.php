<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Vínculo opcional com o paciente — usado nas tarefas pessoais
            // ("Minhas tarefas"), onde Responsável não faz sentido (é sempre
            // quem criou). Habilita futuramente uma aba "Tarefas relacionadas"
            // no prontuário sem precisar de mais nenhuma mudança de schema.
            $table->foreignId('patient_id')->nullable()->after('assigned_to')->constrained()->nullOnDelete();
            // Fixar — nulo = não fixada; preenchido = fixada, e a data serve
            // de critério de ordenação entre as fixadas (mais recente primeiro).
            $table->timestamp('pinned_at')->nullable()->after('position');
            $table->boolean('is_favorite')->default(false)->after('pinned_at');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('patient_id');
            $table->dropColumn(['pinned_at', 'is_favorite']);
        });
    }
};
