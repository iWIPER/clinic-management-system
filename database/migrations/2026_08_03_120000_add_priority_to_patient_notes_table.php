<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('patient_notes', function (Blueprint $table) {
            // Só é significativo quando is_alert = true; is_alert continua sendo
            // o único responsável por aparecer no badge do topo do perfil.
            $table->string('priority', 20)->nullable()->after('is_alert');
        });

        // hasIndex() evita erro de índice duplicado num ciclo migrate → rollback
        // → migrate: down() não remove este índice (ver comentário em down()),
        // então ele pode já existir aqui.
        if (! Schema::hasIndex('patient_notes', 'patient_notes_patient_id_index')) {
            Schema::table('patient_notes', function (Blueprint $table) {
                $table->index('patient_id');
            });
        }

        // Alertas já existentes eram, por definição, o nível mais grave disponível hoje.
        DB::table('patient_notes')->where('is_alert', true)->update(['priority' => 'critico']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // O índice em patient_id não é revertido: no MySQL/InnoDB ele passou a
        // ser a única estrutura suportando a foreign key de patient_id (o índice
        // implícito original da FK foi automaticamente descartado como redundante
        // assim que este índice foi criado) — removê-lo quebraria a FK. Deixar o
        // índice é inofensivo (é só um ganho de performance).
        Schema::table('patient_notes', function (Blueprint $table) {
            $table->dropColumn('priority');
        });
    }
};
