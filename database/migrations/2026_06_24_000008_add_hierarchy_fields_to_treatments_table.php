<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatments', function (Blueprint $table) {
            $table->string('categoria')->nullable()->after('nome');
            $table->string('tipo')->default('procedimento')->after('categoria'); // procedimento, variacao, grupo
            $table->foreignId('parent_id')->nullable()->after('tipo')->constrained('treatments')->nullOnDelete();
            $table->string('cor', 7)->default('#10b981')->after('descricao');
            $table->unsignedInteger('ordem')->default(0)->after('cor');
            $table->string('catalog_slug')->nullable()->after('ordem');

            $table->unique(['clinic_id', 'catalog_slug']);
            $table->index(['clinic_id', 'categoria', 'ordem']);
        });
    }

    public function down(): void
    {
        Schema::table('treatments', function (Blueprint $table) {
            $table->dropUnique(['clinic_id', 'catalog_slug']);
            $table->dropIndex(['clinic_id', 'categoria', 'ordem']);
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn(['categoria', 'tipo', 'cor', 'ordem', 'catalog_slug']);
        });
    }
};