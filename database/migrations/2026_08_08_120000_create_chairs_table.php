<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chairs', function (Blueprint $table) {
            $table->id();
            // Ao contrário de task_labels (clinic_id nulo = global), uma
            // cadeira é sempre um recurso físico de UMA clínica — nunca
            // compartilhada entre tenants.
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 20)->default('#0d9488');
            $table->timestamps();

            $table->index(['clinic_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chairs');
    }
};
