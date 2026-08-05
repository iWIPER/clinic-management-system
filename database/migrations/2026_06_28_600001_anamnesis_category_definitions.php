<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anamnesis_category_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('icon', 16)->default('📄');
            $table->string('icon_color', 16)->default('#64748b');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['clinic_id', 'slug']);
        });

        Schema::table('anamnesis_questions', function (Blueprint $table) {
            $table->foreignId('category_id')
                ->nullable()
                ->after('category')
                ->constrained('anamnesis_category_definitions')
                ->nullOnDelete();
        });

        Schema::table('anamnesis_templates', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('anamnesis_templates', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });

        Schema::table('anamnesis_questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });

        Schema::dropIfExists('anamnesis_category_definitions');
    }
};