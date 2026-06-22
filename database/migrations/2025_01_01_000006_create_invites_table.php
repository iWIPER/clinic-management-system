<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role'); // owner | admin | professional | staff
            $table->string('token')->unique();
            $table->timestamp('expires_at');
            $table->foreignId('invited_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['clinic_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invites');
    }
};
