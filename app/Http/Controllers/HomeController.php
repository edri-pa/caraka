<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController
{
    public function index(): View
    {
        return view('beranda');
    }

    public function checkStatus(Request $request): RedirectResponse
    {
        $referensi = strtoupper((string) preg_replace('/[\s\-]+/', '', (string) $request->input('referensi')));

        if (preg_match('/^[A-Z0-9]{10}$/', $referensi) !== 1) {
            return redirect()->route('beranda')
                ->withErrors(['referensi' => 'Nomor referensi terdiri dari 10 huruf dan angka.']);
        }

        return redirect()->route('lapor.show', $referensi);
    }
}
