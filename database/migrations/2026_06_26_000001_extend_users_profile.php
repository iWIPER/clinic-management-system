<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('cpf', 14)->nullable()->after('phone');
            $table->date('birth_date')->nullable()->after('cpf');
            $table->string('gender', 30)->nullable()->after('birth_date');
            $table->string('cro', 10)->nullable()->after('gender');
            $table->string('cro_uf', 2)->nullable()->after('cro');
            $table->string('specialty')->nullable()->after('cro_uf');
            $table->string('job_title')->nullable()->after('specialty');
            $table->string('status', 20)->default('ativo')->after('job_title');
            $table->string('profile_photo_path')->nullable()->after('status');
            $table->timestamp('last_login_at')->nullable()->after('profile_photo_path');
            $table->timestamp('profile_updated_at')->nullable()->after('last_login_at');
            $table->json('preferences')->nullable()->after('profile_updated_at');
        });

        Schema::create('user_profile_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action');
            $table->string('field')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profile_activity_logs');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'cpf',
                'birth_date',
                'gender',
                'cro',
                'cro_uf',
                'specialty',
                'job_title',
                'status',
                'profile_photo_path',
                'last_login_at',
                'profile_updated_at',
                'preferences',
            ]);
        });
    }
};