<?php

namespace App\Enums\Documents;

enum DocumentStatus: string
{
    case Draft              = 'draft';
    case Issued              = 'issued';
    case AwaitingSignature   = 'awaiting_signature';
    case PatientSigned       = 'patient_signed';
    case ProfessionalSigned  = 'professional_signed';
    case Completed           = 'completed';
    case Cancelled           = 'cancelled';
    case Expired             = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Draft             => 'Rascunho',
            self::Issued            => 'Emitido',
            self::AwaitingSignature => 'Aguardando assinatura',
            self::PatientSigned     => 'Paciente assinou',
            self::ProfessionalSigned => 'Profissional assinou',
            self::Completed         => 'Concluído',
            self::Cancelled         => 'Cancelado',
            self::Expired           => 'Expirado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft              => 'slate',
            self::Issued             => 'blue',
            self::AwaitingSignature  => 'amber',
            self::PatientSigned      => 'amber',
            self::ProfessionalSigned => 'amber',
            self::Completed          => 'teal',
            self::Cancelled          => 'red',
            self::Expired            => 'red',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Draft              => '○',
            self::Issued             => '◔',
            self::AwaitingSignature  => '⌛',
            self::PatientSigned      => '✎',
            self::ProfessionalSigned => '✎',
            self::Completed          => '✔',
            self::Cancelled          => '✖',
            self::Expired            => '⏰',
        };
    }
}
