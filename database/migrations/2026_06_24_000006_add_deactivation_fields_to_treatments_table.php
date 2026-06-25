<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatments', function (Blueprint $table) {
            $table->timestamp('deactivated_at')->nullable()->after('ativo');
            $table->foreignId('deactivated_by_id')->nullable()->after('deactivated_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('treatments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deactivated_by_id');
            $table->dropColumn('deactivated_at');
        });
    }
};