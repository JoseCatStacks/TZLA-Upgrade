<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Guess extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'wallet_id',
        'word_id',
        'guess_raw',
        'guess_normalized',
        'is_correct',
        'created_at',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function word(): BelongsTo
    {
        return $this->belongsTo(Word::class);
    }
}
