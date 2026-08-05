<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PatientPhoto extends Model
{
    use HasFactory, BelongsToClinic;

    protected $fillable = [
        'clinic_id',
        'patient_id',
        'clinical_evolution_id',
        'drive_file_id',
        'drive_folder_id',
        'local_path',
        'filename',
        'mime_type',
        'taken_at',
        'categoria',
        'subcategoria',
        'dente',
        'description',
        'observacao',
        'status',
        'failure_reason',
        'uploaded_by_id',
        'size_bytes',
    ];

    protected $casts = [
        'taken_at' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function clinicalEvolution()
    {
        return $this->belongsTo(ClinicalEvolution::class);
    }
}
