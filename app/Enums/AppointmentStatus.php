<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Scheduled    = 'scheduled';
    case Confirmed    = 'confirmed';
    case InAttendance = 'in_attendance';
    case Completed    = 'completed';
    case Cancelled    = 'cancelled';
    case NoShow       = 'no_show';

    public function label(): string
    {
        return match($this) {
            self::Scheduled    => 'Agendada',
            self::Confirmed    => 'Confirmada',
            self::InAttendance => 'Em atendimento',
            self::Completed    => 'Concluída',
            self::Cancelled    => 'Cancelada',
            self::NoShow       => 'Faltou',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Scheduled, self::Confirmed, self::InAttendance]);
    }
}
