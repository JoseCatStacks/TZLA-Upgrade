<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WeekCompletion extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'wallet_id',
        'week_id',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
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
