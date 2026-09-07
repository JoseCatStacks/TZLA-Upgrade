<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GameConfigController;
use App\Http\Controllers\Api\GuessController;
use App\Http\Controllers\Api\WeekController;
use App\Http\Controllers\Telegram\BotController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('/nonce', [AuthController::class, 'nonce'])->middleware('throttle:20,1');
    Route::post('/verify', [AuthController::class, 'verify'])->middleware('throttle:20,1');
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/profile', [AuthController::class, 'profile'])->middleware('throttle:30,1');
    Route::get('/me', [AuthController::class, 'me']);
});

Route::get('/game-config', [GameConfigController::class, 'index']);
Route::get('/solana/blockhash', [GameConfigController::class, 'blockhash'])->middleware('throttle:60,1');
Route::post('/solana/send', [GameConfigController::class, 'send'])->middleware('throttle:30,1');

Route::get('/weeks', [WeekController::class, 'index']);
Route::get('/weeks/{number}', [WeekController::class, 'show'])->whereNumber('number');

Route::post('/weeks/{number}/bundle', [GuessController::class, 'submitBundle'])
    ->whereNumber('number')
    ->middleware('throttle:60,1');

Route::post('/telegram/webhook', [BotController::class, 'webhook'])
    ->withoutMiddleware(['auth', 'verified'])
    ->middleware('throttle:60,1');
