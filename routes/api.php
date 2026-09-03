<?php

use App\Http\Controllers\RpcProxyController;
use App\Http\Controllers\StakingController;
use Illuminate\Support\Facades\Route;

Route::post('/rpc', [RpcProxyController::class, 'proxy'])
    ->middleware('throttle:rpc');

Route::get('/staking/pool-stats', [StakingController::class, 'poolStats']);
Route::get('/staking/rewards/{wallet}', [StakingController::class, 'rewards']);
Route::get('/staking/predicted-yield/{wallet}', [StakingController::class, 'predictedYield']);

Route::middleware(['throttle:60,1', 'throttle:staking-writes'])->group(function (): void {
    Route::post('/staking/record-stake', [StakingController::class, 'recordStake']);
    Route::post('/staking/record-unstake', [StakingController::class, 'recordUnstake']);
});
