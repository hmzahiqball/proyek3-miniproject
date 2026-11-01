<?php

namespace App\Models;

use App\Enums\ReportStatus;
use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['area_id', 'title', 'description', 'occurred_at', 'location_description', 'latitude', 'longitude'])]
class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'baru'];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'closed_at' => 'datetime',
            'status' => ReportStatus::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'evidence_size' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /** @return BelongsTo<Area, $this> */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }
}
