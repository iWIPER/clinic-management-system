<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_storage_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider')->default('google');
            $table->text('refresh_token'); // será criptografado no model/service
            $table->text('access_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('status')->default('connected');
            $table->timestamps();
        });

        Schema::create('patient_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('drive_file_id');
            $table->string('drive_folder_id')->nullable();
            $table->string('filename');
            $table->string('mime_type');
            $table->timestamp('taken_at')->nullable();
            $table->string('categoria')->nullable();
            $table->string('dente')->nullable();
            $table->foreignId('uploaded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->bigInteger('size_bytes')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'patient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_photos');
        Schema::dropIfExists('clinic_storage_connections');
    }
};
