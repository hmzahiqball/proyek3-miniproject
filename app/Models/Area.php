<?php

namespace App\Models;

use Database\Factories\AreaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'is_active'])]
class Area extends Model
{
    /** @use HasFactory<AreaFactory> */
    use HasFactory;

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected function code(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => strtoupper(trim($value)));
    }

    /** @return HasMany<Report, $this> */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }
}
