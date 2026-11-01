<?php

namespace App\Enums;

enum ReviewDecision: string
{
    case PerluPerbaikan = 'perlu_perbaikan';
    case PerluDiteruskan = 'perlu_diteruskan';
    case Selesai = 'selesai';

    public function label(): string
    {
        return match ($this) {
            self::PerluPerbaikan => 'Perlu perbaikan',
            self::PerluDiteruskan => 'Perlu diteruskan',
            self::Selesai => 'Selesai',
        };
    }
}
