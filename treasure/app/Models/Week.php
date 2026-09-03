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
        $max = (int) config('game.weeks.max_playable', PHP_INT_MAX);

        return $query
            ->where('active', true)
            ->where('starts_at', '<=', now())
            ->where('number', '<=', $max);
    }

    public function isUnlocked(): bool
    {
        if (! $this->active || $this->starts_at === null || ! $this->starts_at->isPast()) {
            return false;
        }

        $max = (int) config('game.weeks.max_playable', PHP_INT_MAX);

        return $this->number <= $max;
    }
}
