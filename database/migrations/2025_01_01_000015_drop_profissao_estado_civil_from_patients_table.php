<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['profissao', 'estado_civil']);
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('profissao')->nullable()->after('status');
            $table->string('estado_civil')->nullable()->after('profissao');
        });
    }
};