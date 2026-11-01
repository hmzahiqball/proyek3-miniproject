<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $attributes = [
        'role' => 'pelapor',
        'is_active' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<Report, $this> */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'reporter_id');
    }

    /** @return HasMany<ReportReview, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(ReportReview::class, 'reviewer_id');
    }

    /** @return HasMany<ReportForwarding, $this> */
    public function forwardings(): HasMany
    {
        return $this->hasMany(ReportForwarding::class, 'coordinator_id');
    }

    /** @return HasMany<ReportStatusLog, $this> */
    public function statusChanges(): HasMany
    {
        return $this->hasMany(ReportStatusLog::class, 'changed_by');
    }
}
