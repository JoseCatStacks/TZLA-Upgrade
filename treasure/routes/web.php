<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::view('/how-it-works', 'how-it-works')->name('how-it-works');
