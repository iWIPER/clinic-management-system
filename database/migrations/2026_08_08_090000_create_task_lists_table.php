<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // 'mine'|'team' hoje — nulo reservado pra uma futura lista
            // customizada criada pelo próprio usuário (sem redesenho de schema).
            $table->string('key', 20)->nullable();
            $table->string('name');
            $table->string('color', 20);
            // private = só o dono vê; team = todo mundo da clínica; selected =
            // só quem estiver em task_list_shares.
            $table->string('sharing_type', 20)->default('private');
            $table->timestamps();

            $table->unique(['clinic_id', 'user_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_lists');
    }
};
