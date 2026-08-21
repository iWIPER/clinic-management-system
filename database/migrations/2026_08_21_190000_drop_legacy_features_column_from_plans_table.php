<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A coluna JSON legada plans.features foi substituída pela tabela
// relacional plan_features (ver 2026_06_26_300002_create_plans_table.php)
// há tempos — nenhum código lê mais o valor bruto dela. Ela continuar
// existindo colide com o método de relação Plan::features(): Eloquent
// resolve `$plan->features` como o atributo da coluna (sempre, indepen-
// dente de cast/fillable) em vez de chamar a relação, quebrando
// Admin\PlanController::index() ("Call to a member function map() on
// array/string").
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('features');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->json('features')->nullable();
        });
    }
};
