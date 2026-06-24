<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_user', function (Blueprint $table) {
            $table->string('drive_doctor_folder_id')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('clinic_user', function (Blueprint $table) {
            $table->dropColumn('drive_doctor_folder_id');
        });
    }
};
