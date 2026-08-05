<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invites', function (Blueprint $table) {
            $table->string('name', 255)->nullable()->after('email');
            $table->string('job_title', 100)->nullable()->after('role');
            $table->string('short_token', 10)->nullable()->unique()->after('token');
            $table->enum('status', ['pending', 'accepted', 'expired', 'cancelled'])
                  ->default('pending')->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('invites', function (Blueprint $table) {
            $table->dropColumn(['name', 'job_title', 'short_token', 'status']);
        });
    }
};
