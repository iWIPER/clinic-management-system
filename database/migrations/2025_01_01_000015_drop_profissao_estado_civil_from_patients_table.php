<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = array_filter(
            ['profissao', 'estado_civil'],
            fn (string $column) => Schema::hasColumn('patients', $column)
        );

        if ($columns !== []) {
            Schema::table('patients', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('profissao')->nullable()->after('status');
            $table->string('estado_civil')->nullable()->after('profissao');
        });
    }
};