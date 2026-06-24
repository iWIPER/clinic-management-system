<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_storage_connections', function (Blueprint $table) {
            $table->string('google_email')->nullable()->after('provider');
        });
    }

    public function down(): void
    {
        Schema::table('clinic_storage_connections', function (Blueprint $table) {
            $table->dropColumn('google_email');
        });
    }
};
