<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->string('logo_type')->default('default')->after('logo_path');
            $table->string('default_logo')->nullable()->after('logo_type');
        });

        // Clínicas que já possuem logo personalizado → marcar como 'custom'
        DB::table('clinics')->whereNotNull('logo_path')->update(['logo_type' => 'custom']);
    }

    public function down(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->dropColumn(['logo_type', 'default_logo']);
        });
    }
};
