<?php

namespace App\Enums;

enum UserRole: string
{
    case Pelapor = 'pelapor';
    case Relawan = 'relawan';
    case Koordinator = 'koordinator';
}
