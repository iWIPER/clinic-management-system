<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procedure_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consultation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('treatment_id')->constrained()->cascadeOnDelete();
            $table->timestamp('executed_at');
            $table->decimal('price_charged', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procedure_executions');
    }
};
