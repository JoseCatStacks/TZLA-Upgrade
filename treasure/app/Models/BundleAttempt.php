<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BundleAttempt extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'wallet_id',
        'week_id',
        'correct_count',
        'total_words',
        'is_complete',
        'answers',
        'fee_signature',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'is_complete' => 'boolean',
        'correct_count' => 'integer',
        'total_words' => 'integer',
        'answers' => 'array',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function week(): BelongsTo
    {
        return $this->belongsTo(Week::class);
    }
}
