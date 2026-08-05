<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anamnesis_instances', function (Blueprint $table) {
            $table->json('disabled_question_ids')->nullable()->after('pdf_path');
        });

        Schema::table('anamnesis_questions', function (Blueprint $table) {
            $table->unsignedBigInteger('instance_id')->nullable()->after('clinic_id');
            $table->foreign('instance_id')->references('id')->on('anamnesis_instances')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('anamnesis_questions', function (Blueprint $table) {
            $table->dropForeign(['instance_id']);
            $table->dropColumn('instance_id');
        });

        Schema::table('anamnesis_instances', function (Blueprint $table) {
            $table->dropColumn('disabled_question_ids');
        });
    }
};
