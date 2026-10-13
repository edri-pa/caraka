<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/uji-reverb', fn () => view('uji-reverb'));
Route::get('/uji-gps', fn () => view('uji-gps'));
