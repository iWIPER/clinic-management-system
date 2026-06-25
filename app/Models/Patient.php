<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use HasFactory, BelongsToClinic;

    protected $fillable = [
        'clinic_id',
        'nome',
        'sobrenome',
        'nascimento',
        'status',             // ativo, inativo, falecido
        'doc_tipo',           // cpf, rg, passaporte
        'doc_numero',
        'telefone',
        'email',
        'contato_emergencia_nome',
        'contato_emergencia_telefone',
        'cep',
        'logradouro',
        'numero',
        'complemento',
        'bairro',
        'cidade',
        'estado',
        'drive_folder_id',    // Google Drive do paciente (criado no 1º upload)
        'observacoes',
    ];

    protected $casts = [
        'nascimento' => 'date',
    ];

    public function getNomeCompletoAttribute(): string
    {
        return trim($this->nome . ' ' . $this->sobrenome);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function consultations()
    {
        return $this->hasMany(Consultation::class);
    }

    public function budgets()
    {
        return $this->hasMany(Budget::class);
    }

    public function photos()
    {
        return $this->hasMany(PatientPhoto::class);
    }

    public function clinicalRecords()
    {
        return $this->hasMany(ClinicalRecord::class);
    }

    public function anamnesis()
    {
        return $this->hasOne(PatientAnamnesis::class);
    }

    public function evolutions()
    {
        return $this->hasMany(ClinicalEvolution::class);
    }

    public function odontogram()
    {
        return $this->hasOne(PatientOdontogram::class);
    }
}
