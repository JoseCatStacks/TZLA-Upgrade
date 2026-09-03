<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Word extends Model
{
    use HasFactory;

    protected $fillable = [
        'week_id',
        'position',
        'answer_normalized',
        'hint',
    ];

    protected $casts = [
        'week_id' => 'integer',
        'position' => 'integer',
    ];

    public function week(): BelongsTo
    {
        return $this->belongsTo(Week::class);
    }

    public function guesses(): HasMany
    {
        return $this->hasMany(Guess::class);
    }

    public function completions(): HasMany
    {
        return $this->hasMany(WordCompletion::class);
    }
}
