<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Week extends Model
{
    use HasFactory;

    protected $fillable = [
        'number',
        'active',
        'title',
        'starts_at',
        'reward_description',
        'reward_claimed',
    ];

    protected $casts = [
        'number' => 'integer',
        'active' => 'boolean',
        'reward_claimed' => 'boolean',
        'starts_at' => 'datetime',
    ];

    public function words(): HasMany
    {
        return $this->hasMany(Word::class)->orderBy('position');
    }

    public function completions(): HasMany
    {
        return $this->hasMany(WeekCompletion::class);
    }

    public function scopeUnlocked(Builder $query): Builder
    {
        return $query->where('active', true)->where('starts_at', '<=', now());
    }

    public function isUnlocked(): bool
    {
        return $this->active && $this->starts_at !== null && $this->starts_at->isPast();
    }
}
