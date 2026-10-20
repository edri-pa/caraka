<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    $destinations = [
        'dispatcher' => '/dispatcher/laporan',
        'responder' => '/responder/tugas',
        'admin' => '/dispatcher/laporan',
    ];

    $target = $destinations[auth()->user()->role] ?? null;

    abort_if($target === null, 403, 'Peran akun ini belum memiliki halaman tujuan.');

    return redirect($target);
})->middleware('auth');

// Pelaporan warga (tanpa login).
Route::get('/lapor', [\App\Http\Controllers\IncidentReportController::class, 'create'])
    ->name('lapor.create');
Route::post('/lapor', [\App\Http\Controllers\IncidentReportController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('lapor.store');
Route::get('/lapor/{reference}', [\App\Http\Controllers\IncidentReportController::class, 'show'])
    ->where('reference', '[A-Z0-9]{10}')
    ->middleware('throttle:30,1')
    ->name('lapor.show');

// Dashboard dispatcher.
Route::get('/dispatcher/laporan', [\App\Http\Controllers\DispatcherIncidentController::class, 'index'])
    ->middleware(['auth', \App\Http\Middleware\EnsureUserHasRole::class.':dispatcher,admin'])
    ->name('dispatcher.laporan');

// Detail laporan untuk staf (hak akses per peran diperiksa di controller).
Route::middleware(['auth', \App\Http\Middleware\EnsureUserHasRole::class.':dispatcher,admin,responder'])
    ->prefix('staf')
    ->group(function () {
        Route::get('/laporan/{id}', [\App\Http\Controllers\StaffIncidentController::class, 'show'])
            ->whereNumber('id')->name('staf.laporan.show');
        Route::post('/laporan/{id}/status', [\App\Http\Controllers\StaffIncidentController::class, 'updateStatus'])
            ->whereNumber('id')->name('staf.laporan.status');
        Route::post('/laporan/{id}/instansi', [\App\Http\Controllers\StaffIncidentController::class, 'assignAgency'])
            ->whereNumber('id')->name('staf.laporan.instansi');
        Route::post('/laporan/{id}/saran', [\App\Http\Controllers\StaffIncidentController::class, 'applySuggestions'])
            ->whereNumber('id')->name('staf.laporan.saran');
        Route::post('/laporan/{id}/tanda', [\App\Http\Controllers\StaffIncidentController::class, 'flag'])
            ->whereNumber('id')->name('staf.laporan.tanda');
        Route::post('/laporan/{id}/tanda/hapus', [\App\Http\Controllers\StaffIncidentController::class, 'clearFlag'])
            ->whereNumber('id')->name('staf.laporan.tanda.hapus');
    });

Route::get('/dispatcher/laporan/ringkasan', [\App\Http\Controllers\DispatcherIncidentController::class, 'summary'])
    ->middleware(['auth', \App\Http\Middleware\EnsureUserHasRole::class.':dispatcher,admin'])
    ->name('dispatcher.ringkasan');

// Daftar tugas responder.
Route::get('/responder/tugas', [\App\Http\Controllers\ResponderTaskController::class, 'index'])
    ->middleware(['auth', \App\Http\Middleware\EnsureUserHasRole::class.':responder'])
    ->name('responder.tugas');
