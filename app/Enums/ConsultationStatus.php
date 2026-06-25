<?php

namespace App\Enums;

enum ConsultationStatus: string
{
    case Waiting      = 'aguardando';
    case InAttendance = 'em_atendimento';
    case Finished     = 'finalizado';
    case Cancelled    = 'cancelado';

    public function label(): string
    {
        return match($this) {
            self::Waiting      => 'Aguardando',
            self::InAttendance => 'Em atendimento',
            self::Finished     => 'Finalizado',
            self::Cancelled    => 'Cancelado',
        };
    }
}
