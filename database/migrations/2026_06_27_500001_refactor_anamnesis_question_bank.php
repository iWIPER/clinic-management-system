<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anamnesis_questions', function (Blueprint $table) {
            $table->string('category')->nullable()->after('id');
            $table->foreignId('clinic_id')->nullable()->after('category')->constrained()->nullOnDelete();
            $table->string('question_hash', 64)->nullable()->after('text');
        });

        Schema::create('anamnesis_template_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('anamnesis_templates')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('anamnesis_questions')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_required')->default(false);
            $table->timestamps();

            $table->unique(['template_id', 'question_id']);
        });

        if (Schema::hasTable('anamnesis_questions') && DB::table('anamnesis_questions')->exists()) {
            $this->migrateExistingQuestions();
        }

        Schema::table('anamnesis_questions', function (Blueprint $table) {
            if (Schema::hasColumn('anamnesis_questions', 'template_id')) {
                $table->dropForeign(['template_id']);
                $table->dropColumn('template_id');
            }
            if (Schema::hasColumn('anamnesis_questions', 'category_id')) {
                $table->dropForeign(['category_id']);
                $table->dropColumn('category_id');
            }
            if (Schema::hasColumn('anamnesis_questions', 'sort_order')) {
                $table->dropColumn('sort_order');
            }
        });

        Schema::dropIfExists('anamnesis_categories');
    }

    public function down(): void
    {
        Schema::create('anamnesis_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('anamnesis_templates')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::dropIfExists('anamnesis_template_questions');

        Schema::table('anamnesis_questions', function (Blueprint $table) {
            $table->dropColumn(['category', 'clinic_id', 'question_hash']);
            $table->foreignId('template_id')->nullable()->constrained('anamnesis_templates')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('anamnesis_categories')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
        });
    }

    private function migrateExistingQuestions(): void
    {
        $questions = DB::table('anamnesis_questions')
            ->leftJoin('anamnesis_categories', 'anamnesis_questions.category_id', '=', 'anamnesis_categories.id')
            ->select(
                'anamnesis_questions.*',
                'anamnesis_categories.name as category_name'
            )
            ->orderBy('anamnesis_questions.id')
            ->get();

        $canonical = [];

        foreach ($questions as $row) {
            $hash = hash('sha256', mb_strtolower(trim($row->text)));
            $category = $row->category_name ?? $row->category ?? 'GERAL';

            if (! isset($canonical[$hash])) {
                DB::table('anamnesis_questions')->where('id', $row->id)->update([
                    'category' => $category,
                    'question_hash' => $hash,
                ]);
                $canonical[$hash] = $row->id;
            } else {
                $canonicalId = $canonical[$hash];

                DB::table('anamnesis_answers')->where('question_id', $row->id)->update([
                    'question_id' => $canonicalId,
                ]);
                DB::table('anamnesis_alerts')->where('question_id', $row->id)->update([
                    'question_id' => $canonicalId,
                ]);

                DB::table('anamnesis_questions')->where('id', $row->id)->delete();
                $row->id = $canonicalId;
            }

            $exists = DB::table('anamnesis_template_questions')
                ->where('template_id', $row->template_id)
                ->where('question_id', $canonical[$hash])
                ->exists();

            if (! $exists && $row->template_id) {
                DB::table('anamnesis_template_questions')->insert([
                    'template_id' => $row->template_id,
                    'question_id' => $canonical[$hash],
                    'sort_order' => $row->sort_order ?? 0,
                    'is_required' => (bool) $row->is_required,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
};