<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->string('trade_name')->nullable()->after('name');
            $table->string('slogan')->nullable()->after('trade_name');
            $table->string('logo_path')->nullable()->after('slogan');
        });
    }

    public function down(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->dropColumn(['trade_name', 'slogan', 'logo_path']);
        });
    }
};