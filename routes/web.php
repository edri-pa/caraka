<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/uji-reverb', fn () => view('uji-reverb'));
Route::get('/uji-gps', fn () => view('uji-gps'));


// Rute uji pembatasan peran (sementara, dihapus sebelum deploy).
Route::get('/uji-peran/dispatcher', fn () => 'Halaman dispatcher')
    ->middleware(['auth', \App\Http\Middleware\EnsureUserHasRole::class.':dispatcher,admin']);

Route::get('/uji-peran/responder', fn () => 'Halaman responder')
    ->middleware(['auth', \App\Http\Middleware\EnsureUserHasRole::class.':responder,admin']);

Route::get('/uji-peran/admin', fn () => 'Halaman admin')
    ->middleware(['auth', \App\Http\Middleware\EnsureUserHasRole::class.':admin']);

Route::get('/dashboard', function () {
    $destinations = [
        'dispatcher' => '/uji-peran/dispatcher',
        'responder' => '/uji-peran/responder',
        'admin' => '/uji-peran/admin',
    ];

    $target = $destinations[auth()->user()->role] ?? null;

    abort_if($target === null, 403, 'Peran akun ini belum memiliki halaman tujuan.');

    return redirect($target);
})->middleware('auth');

Route::get('/uji-keluar', fn () => view('uji-keluar'))->middleware('auth');
