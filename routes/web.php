<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/uji-reverb', fn () => view('uji-reverb'));
Route::get('/uji-gps', fn () => view('uji-gps'));

Route::get('/dashboard', function () {
    $user = auth()->user();

    return 'Login berhasil: '.e($user->name).' ('.e($user->role).')';
})->middleware('auth');
