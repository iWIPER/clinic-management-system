<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('patient_photos', function (Blueprint $table) {
            // Guarda o arquivo localmente (disco "local", fora do Drive)
            // quando o upload pro Google Drive falha na hora de criar a
            // evolução — permite reenviar depois sem pedir o arquivo de
            // novo pro usuário. status='pending' enquanto isso; some
            // (registro é apagado) assim que o reenvio for bem-sucedido.
            $table->string('local_path')->nullable()->after('drive_folder_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient_photos', function (Blueprint $table) {
            $table->dropColumn('local_path');
        });
    }
};
