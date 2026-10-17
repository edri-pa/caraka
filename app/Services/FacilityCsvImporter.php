<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class FacilityCsvImporter
{
    /** jenis fasilitas => jenis instansi yang menaungi (null = tanpa instansi) */
    public const TYPES = [
        'rumah_sakit' => 'medis',
        'puskesmas' => 'medis',
        'klinik' => 'medis',
        'pmi' => 'medis',
        'pos_damkar' => 'damkar',
        'polsek' => 'polisi',
        'polres' => 'polisi',
        'kantor_bpbd' => 'bpbd',
        'lainnya' => null,
    ];

    // Kotak kasar yang longgar di sekitar Temanggung dan kabupaten tetangga.
    // Hanya pagar pengaman terhadap salah ketik, BUKAN batas wilayah resmi.
    public const LAT_MIN = -7.70;
    public const LAT_MAX = -6.90;
    public const LNG_MIN = 109.80;
    public const LNG_MAX = 110.55;

    private const REQUIRED = ['nama', 'jenis', 'lintang', 'bujur', 'sumber', 'tanggal_cek'];

    /**
     * @return array{rows: array<int, array<string, mixed>>, errors: array<int, string>, warnings: array<int, string>}
     */
    public function parse(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            return ['rows' => [], 'errors' => ['Berkas tidak ditemukan atau tidak bisa dibaca.'], 'warnings' => []];
        }

        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        $firstLine = explode("\n", $content, 2)[0];
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $header = fgetcsv($handle, 0, $delimiter, '"', '');

        if ($header === false || $header === [null]) {
            fclose($handle);

            return ['rows' => [], 'errors' => ['Berkas kosong.'], 'warnings' => []];
        }

        $header = array_map(fn ($h) => str_replace(' ', '_', strtolower(trim((string) $h))), $header);
        $missing = array_values(array_diff(self::REQUIRED, $header));

        if ($missing !== []) {
            fclose($handle);

            return [
                'rows' => [],
                'errors' => ['Kolom wajib tidak ada: '.implode(', ', $missing).'.'],
                'warnings' => [],
            ];
        }

        $districts = DB::table('districts')->get(['id', 'name'])
            ->mapWithKeys(fn ($d) => [mb_strtolower(trim($d->name)) => (int) $d->id]);
        $agencies = DB::table('agencies')->orderBy('id')->get(['id', 'type'])
            ->unique('type')
            ->mapWithKeys(fn ($a) => [$a->type => (int) $a->id]);

        $errors = [];
        $warnings = [];
        $rows = [];
        $seen = [];
        $lineNo = 1;

        while (($record = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $lineNo++;

            if (count(array_filter($record, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $data = [];
            foreach ($header as $i => $column) {
                $data[$column] = trim((string) ($record[$i] ?? ''));
            }

            $rowErrors = [];

            $name = $data['nama'] ?? '';
            if ($name === '' || mb_strlen($name) > 150) {
                $rowErrors[] = 'nama wajib diisi dan maksimal 150 karakter.';
            }

            $type = strtolower($data['jenis'] ?? '');
            if (! array_key_exists($type, self::TYPES)) {
                $rowErrors[] = "jenis '{$type}' tidak dikenal. Pilihan: ".implode(', ', array_keys(self::TYPES)).'.';
            }

            $region = ($data['wilayah'] ?? '') !== '' ? $data['wilayah'] : 'Temanggung';

            $key = mb_strtolower($region.'|'.$name);
            if ($name !== '' && isset($seen[$key])) {
                $rowErrors[] = "nama ganda dalam berkas (sama dengan baris {$seen[$key]}).";
            }
            $seen[$key] ??= $lineNo;

            $lat = $this->number($data['lintang'] ?? '', $delimiter);
            $lng = $this->number($data['bujur'] ?? '', $delimiter);

            if ($lat === null || $lng === null) {
                $rowErrors[] = 'lintang dan bujur harus berupa angka (contoh -7.3168 dan 110.1760).';
            } elseif ($lat >= self::LNG_MIN && $lat <= self::LNG_MAX && $lng >= self::LAT_MIN && $lng <= self::LAT_MAX) {
                $rowErrors[] = 'lintang dan bujur tampak tertukar (lintang di sekitar -7, bujur di sekitar 110).';
            } elseif ($lat > 0 && -$lat >= self::LAT_MIN && -$lat <= self::LAT_MAX) {
                $rowErrors[] = 'lintang wilayah ini adalah lintang selatan dan harus bertanda minus (contoh -7.32).';
            } elseif ($lat < self::LAT_MIN || $lat > self::LAT_MAX || $lng < self::LNG_MIN || $lng > self::LNG_MAX) {
                $rowErrors[] = 'koordinat di luar kotak pengaman sekitar Temanggung dan kabupaten tetangga. Periksa kembali.';
            }

            $districtId = null;
            $kecamatan = $data['kecamatan'] ?? '';
            if ($region === 'Temanggung' && $kecamatan !== '') {
                $districtId = $districts[mb_strtolower($kecamatan)] ?? null;

                if ($districtId === null) {
                    $rowErrors[] = "kecamatan '{$kecamatan}' tidak ada di daftar kecamatan.";
                }
            }

            $is24h = $this->bool($data['buka_24_jam'] ?? '');
            $hasIgd = $this->bool($data['ada_igd'] ?? '');

            if ($is24h === null) {
                $rowErrors[] = 'buka_24_jam harus ya atau tidak.';
            }
            if ($hasIgd === null) {
                $rowErrors[] = 'ada_igd harus ya atau tidak.';
            }

            $contact = $data['kontak'] ?? '';
            $verified = $this->bool($data['kontak_terverifikasi'] ?? '') === true;
            $isPlaceholder = $contact === '' || str_starts_with(strtoupper($contact), 'DEMO-');

            if (! $isPlaceholder && ! $verified) {
                $rowErrors[] = 'kontak harus kosong atau berawalan DEMO-. Isi kontak_terverifikasi=ya hanya jika nomor sudah dicek ke sumber resmi.';
            }

            if (($data['sumber'] ?? '') === '') {
                $rowErrors[] = 'sumber wajib diisi (dari mana koordinat dan data diperoleh).';
            }

            $date = $data['tanggal_cek'] ?? '';
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) !== 1 || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                $rowErrors[] = 'tanggal_cek harus berformat TTTT-BB-HH (contoh 2026-10-18).';
            } elseif ($date > now()->addDay()->format('Y-m-d')) {
                $rowErrors[] = 'tanggal_cek berada di masa depan.';
            }

            $agencyType = self::TYPES[$type] ?? null;
            $agencyId = $agencyType !== null ? ($agencies[$agencyType] ?? null) : null;

            if ($agencyType !== null && $agencyId === null) {
                $warnings[] = "Baris {$lineNo}: belum ada instansi bertipe {$agencyType}; fasilitas akan disimpan tanpa instansi.";
            }

            if ($rowErrors !== []) {
                foreach ($rowErrors as $error) {
                    $errors[] = "Baris {$lineNo}: {$error}";
                }

                continue;
            }

            $rows[] = [
                'line' => $lineNo,
                'name' => $name,
                'type' => $type,
                'region' => $region,
                'district_id' => $districtId,
                'agency_id' => $agencyId,
                'lat' => $lat,
                'lng' => $lng,
                'phone' => $contact === '' ? null : $contact,
                'is_24h' => $is24h,
                'has_igd' => $hasIgd,
                'is_demo' => $isPlaceholder,
                'source' => $data['sumber'],
                'verified_at' => $date,
            ];
        }

        fclose($handle);

        return ['rows' => $rows, 'errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Simpan baris yang sudah lolos pemeriksaan (semua atau tidak sama sekali).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{dibuat: int, diperbarui: int}
     */
    public function save(array $rows): array
    {
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($rows, &$created, &$updated) {
            foreach ($rows as $row) {
                $values = [
                    'agency_id' => $row['agency_id'],
                    'district_id' => $row['district_id'],
                    'type' => $row['type'],
                    'location' => DB::raw(sprintf('ST_SetSRID(ST_MakePoint(%F, %F), 4326)::geography', $row['lng'], $row['lat'])),
                    'phone' => $row['phone'],
                    'is_24h' => $row['is_24h'],
                    'has_igd' => $row['has_igd'],
                    'is_active' => true,
                    'is_demo' => $row['is_demo'],
                    'source' => $row['source'],
                    'verified_at' => $row['verified_at'],
                    'updated_at' => now(),
                ];

                $query = DB::table('facilities')
                    ->where('name', $row['name'])
                    ->where('region', $row['region']);

                if ($query->exists()) {
                    $query->update($values);
                    $updated++;
                } else {
                    DB::table('facilities')->insert($values + [
                        'name' => $row['name'],
                        'region' => $row['region'],
                        'created_at' => now(),
                    ]);
                    $created++;
                }
            }
        });

        return ['dibuat' => $created, 'diperbarui' => $updated];
    }

    private function number(string $value, string $delimiter): ?float
    {
        $value = trim($value);

        if ($delimiter === ';') {
            $value = str_replace(',', '.', $value);
        }

        return preg_match('/^-?\d+(\.\d+)?$/', $value) === 1 ? (float) $value : null;
    }

    private function bool(string $value): ?bool
    {
        $v = mb_strtolower(trim($value));

        if (in_array($v, ['ya', 'y', '1', 'true', 'benar'], true)) {
            return true;
        }

        if (in_array($v, ['', 'tidak', 'no', 'n', '0', 'false', 'salah'], true)) {
            return false;
        }

        return null;
    }
}
