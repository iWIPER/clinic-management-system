<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            // todo, doing, waiting, done — hoje só usado como filtro da lista;
            // é também a futura coluna do board Kanban (ver Task::STATUSES).
            $table->string('status', 20)->default('todo');
            // baixa, media, alta, urgente (ver Task::PRIORITIES)
            $table->string('priority', 20)->default('media');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->date('due_date')->nullable();
            // Ordenação dentro da coluna de status — sem UI nesta entrega,
            // existe para o drag-and-drop do Kanban não exigir nova coluna depois.
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'status']);
            $table->index(['clinic_id', 'assigned_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
