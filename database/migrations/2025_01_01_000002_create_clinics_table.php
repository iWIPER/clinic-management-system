<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinics', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type')->default('odontologia'); // odontologia | medicina | etc
            $table->string('cnpj')->nullable();
            $table->enum('status', ['active', 'trial', 'suspended', 'cancelled'])->default('trial');
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();

            // Cashier
            $table->string('stripe_id')->nullable()->index();
            $table->string('pm_type')->nullable();
            $table->string('pm_last_four', 4)->nullable();
            $table->timestamp('trial_ends_at')->nullable();

            $table->json('settings')->nullable();
            $table->timestamp('google_connected_at')->nullable();

            $table->timestamps();
        });

        // Tabela pivot N:N usuário ↔ clínica (NUNCA clinic_id na users)
        Schema::create('clinic_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role'); // owner, admin, professional, staff (chave fixa)
            $table->timestamps();

            $table->unique(['clinic_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_user');
        Schema::dropIfExists('clinics');
    }
};
