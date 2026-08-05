<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anamnesis_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['clinic_id', 'slug']);
        });

        Schema::create('anamnesis_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('anamnesis_templates')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('anamnesis_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('anamnesis_templates')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('anamnesis_categories')->nullOnDelete();
            $table->text('text');
            $table->text('description')->nullable();
            $table->string('type');
            $table->boolean('is_required')->default(false);
            $table->boolean('has_alert')->default(false);
            $table->string('alert_text')->nullable();
            $table->json('alert_trigger_values')->nullable();
            $table->boolean('show_on_patient_card')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('options')->nullable();
            $table->timestamps();
        });

        Schema::create('anamnesis_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->constrained('anamnesis_templates')->restrictOnDelete();
            $table->string('template_name');
            $table->unsignedInteger('template_version')->default(1);
            $table->foreignId('professional_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('draft');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamps();
        });

        Schema::create('anamnesis_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instance_id')->constrained('anamnesis_instances')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('anamnesis_questions')->restrictOnDelete();
            $table->text('question_text');
            $table->string('question_type');
            $table->text('value')->nullable();
            $table->text('supplementary_text')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamps();

            $table->unique(['instance_id', 'question_id']);
        });

        Schema::create('anamnesis_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instance_id')->constrained('anamnesis_instances')->cascadeOnDelete();
            $table->foreignId('answer_id')->nullable()->constrained('anamnesis_answers')->nullOnDelete();
            $table->foreignId('question_id')->nullable()->constrained('anamnesis_questions')->nullOnDelete();
            $table->string('label');
            $table->text('detail')->nullable();
            $table->text('question_text')->nullable();
            $table->text('answer_value')->nullable();
            $table->foreignId('professional_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamp('triggered_at');
            $table->timestamps();
        });

        Schema::create('anamnesis_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instance_id')->nullable()->constrained('anamnesis_instances')->nullOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('anamnesis_templates')->nullOnDelete();
            $table->string('action');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('patient_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('color', 20)->default('#64748b');
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['clinic_id', 'slug']);
        });

        Schema::create('patient_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('color', 20)->default('#64748b');
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_private')->default(false);
            $table->boolean('is_alert')->default(false);
            $table->timestamps();
        });

        Schema::create('patient_note_tag', function (Blueprint $table) {
            $table->foreignId('patient_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['patient_note_id', 'patient_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_note_tag');
        Schema::dropIfExists('patient_notes');
        Schema::dropIfExists('patient_tags');
        Schema::dropIfExists('anamnesis_activity_logs');
        Schema::dropIfExists('anamnesis_alerts');
        Schema::dropIfExists('anamnesis_answers');
        Schema::dropIfExists('anamnesis_instances');
        Schema::dropIfExists('anamnesis_questions');
        Schema::dropIfExists('anamnesis_categories');
        Schema::dropIfExists('anamnesis_templates');
    }
};