<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('related_type');
            $table->unsignedBigInteger('related_id');
            $table->timestamps();

            $table->index(['related_type', 'related_id']);
            $table->unique(['document_id', 'related_type', 'related_id'], 'document_relations_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_relations');
    }
};
