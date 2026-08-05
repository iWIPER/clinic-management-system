<?php

namespace App\Enums\Anamnesis;

enum InstanceStatus: string
{
    case Draft             = 'draft';
    case InProgress        = 'in_progress';
    case Completed         = 'completed';
    case AwaitingSignature = 'awaiting_signature';
    case Signed            = 'signed';
    case FullySigned       = 'fully_signed';
    case Cancelled         = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft             => 'Rascunho',
            self::InProgress        => 'Em andamento',
            self::Completed         => 'Concluída',
            self::AwaitingSignature => 'Aguardando assinatura',
            self::Signed            => 'Paciente assinou',
            self::FullySigned       => 'Completamente assinada',
            self::Cancelled         => 'Cancelada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft             => 'slate',
            self::InProgress        => 'blue',
            self::Completed         => 'emerald',
            self::AwaitingSignature => 'amber',
            self::Signed            => 'amber',
            self::FullySigned       => 'teal',
            self::Cancelled         => 'red',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Draft             => '○',
            self::InProgress        => '◔',
            self::Completed         => '✔',
            self::AwaitingSignature => '⌛',
            self::Signed            => '✎',
            self::FullySigned       => '✔',
            self::Cancelled         => '✖',
        };
    }
}
