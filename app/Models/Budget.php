<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    use HasFactory, BelongsToClinic;

    protected $fillable = [
        'clinic_id',
        'patient_id',
        'status', // rascunho, aprovado, rejeitado, convertido
        'total',
        'valid_until',
        'notes',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'valid_until' => 'date',
    ];

    public function patient() { return $this->belongsTo(Patient::class); }
    public function items() { return $this->hasMany(BudgetItem::class); }
    public function financingProposals() { return $this->hasMany(FinancingProposal::class); }
    public function financingSimulations() { return $this->hasMany(FinancingSimulation::class); }
    public function documents() { return $this->morphToMany(Document::class, 'related', 'document_relations'); }
}
