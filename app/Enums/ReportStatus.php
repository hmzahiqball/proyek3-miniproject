<?php

namespace App\Enums;

enum ReportStatus: string
{
    case Baru = 'baru';
    case Ditinjau = 'ditinjau';
    case PerluPerbaikan = 'perlu_perbaikan';
    case PerluDiteruskan = 'perlu_diteruskan';
    case Diteruskan = 'diteruskan';
    case Selesai = 'selesai';
}
