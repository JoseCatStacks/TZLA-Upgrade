<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WordCompletion extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'wallet_id',
        'word_id',
        'correct_guess_id',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function word(): BelongsTo
    {
        return $this->belongsTo(Word::class);
    }

    public function correctGuess(): BelongsTo
    {
        return $this->belongsTo(Guess::class, 'correct_guess_id');
    }
}
