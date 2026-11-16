<?php

namespace App\Services;

class AppVersion
{
    public function __construct(private ?string $dasar = null)
    {
    }

    public function number(): string
    {
        $versi = trim((string) config('caraka.versi'));

        return $versi !== '' ? $versi : 'tidak diketahui';
    }

    /** Tujuh karakter pertama komit Git, dibaca dari folder .git. Null bila tidak tersedia atau tidak valid. */
    public function commit(): ?string
    {
        $kepala = $this->dasar().'/.git/HEAD';

        if (! is_readable($kepala)) {
            return null;
        }

        $isi = trim((string) file_get_contents($kepala));

        if (str_starts_with($isi, 'ref: ')) {
            $ref = trim(substr($isi, 5));

            if (str_contains($ref, '..')) {
                return null;
            }

            $berkas = $this->dasar().'/.git/'.$ref;
            $sha = is_readable($berkas) ? trim((string) file_get_contents($berkas)) : $this->dariPackedRefs($ref);
        } else {
            $sha = $isi;
        }

        return preg_match('/^[0-9a-f]{40}$/', (string) $sha) === 1 ? substr((string) $sha, 0, 7) : null;
    }

    public function label(bool $denganKomit = false): string
    {
        $versi = $this->number();
        $teks = $versi === 'tidak diketahui' ? 'versi tidak diketahui' : 'v'.$versi;

        if ($denganKomit && ($komit = $this->commit()) !== null) {
            $teks .= ' ('.$komit.')';
        }

        return $teks;
    }

    private function dasar(): string
    {
        return $this->dasar ?? base_path();
    }

    private function dariPackedRefs(string $ref): ?string
    {
        $packed = $this->dasar().'/.git/packed-refs';

        if (! is_readable($packed)) {
            return null;
        }

        foreach (file($packed, FILE_IGNORE_NEW_LINES) ?: [] as $baris) {
            if ($baris !== '' && $baris[0] !== '#' && $baris[0] !== '^' && str_ends_with($baris, ' '.$ref)) {
                return substr($baris, 0, 40);
            }
        }

        return null;
    }
}
