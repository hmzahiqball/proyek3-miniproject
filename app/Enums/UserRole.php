<?php

namespace App\Enums;

enum UserRole: string
{
    case Pelapor = 'pelapor';
    case Relawan = 'relawan';
    case Koordinator = 'koordinator';

    public function label(): string
    {
        return match ($this) {
            self::Pelapor => 'Pelapor',
            self::Relawan => 'Relawan Pemantau',
            self::Koordinator => 'Koordinator Relawan',
        };
    }
}
