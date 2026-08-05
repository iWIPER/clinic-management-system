<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anamnesis_questions', function (Blueprint $table) {
            $table->text('supplementary_placeholder')->nullable()->after('description');
        });

        Schema::table('anamnesis_instances', function (Blueprint $table) {
            $table->timestamp('anamnesis_date')->nullable()->after('started_at');
            $table->string('custom_name')->nullable()->after('template_name');
            $table->string('validation_token', 64)->nullable()->unique()->after('pdf_path');
        });
    }

    public function down(): void
    {
        Schema::table('anamnesis_questions', function (Blueprint $table) {
            $table->dropColumn('supplementary_placeholder');
        });

        Schema::table('anamnesis_instances', function (Blueprint $table) {
            $table->dropColumn(['anamnesis_date', 'custom_name', 'validation_token']);
        });
    }
};
