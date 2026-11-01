<?php

namespace App\Enums;

enum ReviewDecision: string
{
    case PerluPerbaikan = 'perlu_perbaikan';
    case PerluDiteruskan = 'perlu_diteruskan';
    case Selesai = 'selesai';
}
