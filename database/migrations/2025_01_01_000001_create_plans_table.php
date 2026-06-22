<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique(); // start-gratis, starter, pro, premium
            $table->string('stripe_price_id_monthly')->nullable();
            $table->string('stripe_price_id_yearly')->nullable();
            $table->integer('price_monthly_cents')->default(0);
            $table->integer('price_yearly_cents')->default(0);
            $table->json('features'); // { max_clinics: 1, max_patients: 100, max_users: 1, ... }
            $table->integer('max_clinics')->default(1);
            $table->integer('max_patients')->default(100);
            $table->integer('max_users')->default(1);
            $table->integer('storage_gb')->default(1);
            $table->boolean('is_free')->default(false);
            $table->timestamps();
        });

        // Adiciona stripe_id na tabela subscriptions do Cashier (já existe em migrations padrão)
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
