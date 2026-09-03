<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FeePayment extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'signature',
        'wallet_id',
        'word_id',
        'amount_sol',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'amount_sol' => 'decimal:9',
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
