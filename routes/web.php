<?php

use App\Http\Controllers\PageController;
use App\Http\Controllers\PortalController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'welcome'])->name('home');
Route::get('/staking', [PageController::class, 'staking'])->name('staking');
Route::get('/portal/{tab}', [PortalController::class, 'show'])->name('portal');
