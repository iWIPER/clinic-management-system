<?php

namespace App\Enums;

enum ClinicalRecordStatus: string
{
    case Concluido = 'concluido';
    case Cancelado = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::Concluido => 'Concluído',
            self::Cancelado => 'Cancelado',
        };
    }
}