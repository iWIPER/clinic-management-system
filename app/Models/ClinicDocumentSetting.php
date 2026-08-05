<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicDocumentSetting extends Model
{
    protected $fillable = [
        'clinic_id',
        'default_signature_expiration_hours',
        'footer_show_qrcode',
        'footer_show_hash',
        'footer_custom_text',
        'header_show_patient_photo',
        'require_professional_signature_default',
    ];

    protected $casts = [
        'default_signature_expiration_hours'     => 'integer',
        'footer_show_qrcode'                     => 'boolean',
        'footer_show_hash'                       => 'boolean',
        'header_show_patient_photo'               => 'boolean',
        'require_professional_signature_default' => 'boolean',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }
}
