<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinical_evolutions', function (Blueprint $table) {
            $table->foreignId('patient_treatment_id')->nullable()->after('patient_id')
                ->constrained('patient_treatments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clinical_evolutions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('patient_treatment_id');
        });
    }
};
