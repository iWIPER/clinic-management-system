<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('account_type', 20)->default('clinic')->after('email');
            $table->foreignId('invited_by_admin_id')->nullable()->after('account_type')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('invites', function (Blueprint $table) {
            $table->string('type', 20)->default('team')->after('clinic_id');
            $table->unsignedBigInteger('clinic_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('invites', function (Blueprint $table) {
            $table->unsignedBigInteger('clinic_id')->nullable(false)->change();
            $table->dropColumn('type');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invited_by_admin_id');
            $table->dropColumn('account_type');
        });
    }
};
