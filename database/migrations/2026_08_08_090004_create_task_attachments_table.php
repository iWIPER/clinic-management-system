<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Só a tabela — sem controller/rota/upload ainda. Colunas espelham o
     * mesmo padrão de anexo já usado em patient_photos (arquivos ficam no
     * Google Drive da clínica, não em disco local) — quando a funcionalidade
     * for construída, reaproveita o mesmo fluxo de upload que já existe.
     */
    public function up(): void
    {
        Schema::create('task_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('drive_file_id');
            $table->string('drive_folder_id')->nullable();
            $table->string('filename');
            $table->string('mime_type');
            $table->bigInteger('size_bytes')->nullable();
            $table->foreignId('uploaded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_attachments');
    }
};
