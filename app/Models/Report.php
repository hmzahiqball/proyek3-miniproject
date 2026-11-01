<?php

namespace App\Models;

use App\Enums\ReportStatus;
use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    /** @param array<string, mixed> $filters */
    #[Scope]
    protected function matchingFilters(Builder $query, array $filters): void
    {
        if (isset($filters['q'])) {
            $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%';
            $query->where(function (Builder $search) use ($term): void {
                $search->whereRaw("title LIKE ? ESCAPE '!'", [$term])
                    ->orWhereRaw("location_description LIKE ? ESCAPE '!'", [$term]);
            });
        }
        if (isset($filters['area_id'])) {
            $query->where('area_id', $filters['area_id']);
        }
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
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

    /** @return HasMany<ReportReview, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(ReportReview::class);
    }

    /** @return HasOne<ReportForwarding, $this> */
    public function forwarding(): HasOne
    {
        return $this->hasOne(ReportForwarding::class);
    }

    /** @return HasMany<ReportStatusLog, $this> */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(ReportStatusLog::class);
    }
}
