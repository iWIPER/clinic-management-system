<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PatientOdontogram extends Model
{
    use HasFactory, BelongsToClinic;

    public const TOOTH_STATUSES = [
        'saudavel' => 'Saudável',
        'cariado' => 'Cariado',
        'restaurado' => 'Restaurado',
        'ausente' => 'Ausente',
        'endodontia' => 'Endodontia',
        'protese' => 'Prótese',
        'implante' => 'Implante',
        'fraturado' => 'Fraturado',
    ];

    public const FDI_TEETH = [
        '18', '17', '16', '15', '14', '13', '12', '11',
        '21', '22', '23', '24', '25', '26', '27', '28',
        '48', '47', '46', '45', '44', '43', '42', '41',
        '31', '32', '33', '34', '35', '36', '37', '38',
    ];

    public const FDI_DECIDUOUS = [
        '55', '54', '53', '52', '51',
        '61', '62', '63', '64', '65',
        '85', '84', '83', '82', '81',
        '71', '72', '73', '74', '75',
    ];

    protected $fillable = [
        'clinic_id',
        'patient_id',
        'teeth_data',
        'notes',
        'updated_by_id',
    ];

    protected $casts = [
        'teeth_data' => 'array',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    /**
     * `teeth_data` guarda só o estado clínico/visual do dente. Tratamentos
     * (o que antes vivia em `procedures[]` aqui) são geridos exclusivamente
     * por `PatientTreatment` — ver app/Models/PatientTreatment.php.
     */
    public static function defaultTeethData(): array
    {
        $all = array_merge(self::FDI_TEETH, self::FDI_DECIDUOUS);
        return collect($all)
            ->mapWithKeys(fn (string $tooth) => [$tooth => [
                'status'           => 'saudavel',
                'notes'            => '',
                'removed'          => false,
                'removed_at'       => null,
                'removed_by'       => null,
                'removal_reason'   => null,
            ]])
            ->all();
    }
}