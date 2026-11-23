<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class StatisticsCalculationService
{
    /**
     * Menentukan daftar status visibilitas yang diizinkan berdasarkan peran pengguna.
     * - Role 1 (Super Admin) & Role 3 (Viewer Eksekutif): RILIS + PRIVATE
     * - Role 2 (Pegawai BPS) atau unauthenticated: HANYA RILIS
     * - Status DRAFT dan data Trash tidak pernah diizinkan pada kalkulasi dashboard.
     *
     * @param mixed $user
     * @return array<int, string>
     */
    public function getAllowedStatusesForUser($user): array
    {
        if ($user && in_array((int) ($user->role_id ?? 0), [1, 3], true)) {
            return ['RILIS', 'PRIVATE'];
        }

        return ['RILIS'];
    }

    /**
     * Memastikan daftar status visibilitas hanya memuat nilai sah ('RILIS', 'PRIVATE').
     *
     * @param array<int, string> $allowedStatuses
     * @return array<int, string>
     */
    public function sanitizeAllowedStatuses(array $allowedStatuses): array
    {
        $clean = [];
        foreach ($allowedStatuses as $status) {
            $upper = strtoupper(trim((string) $status));
            if (in_array($upper, ['RILIS', 'PRIVATE'], true)) {
                $clean[$upper] = $upper;
            }
        }

        return !empty($clean) ? array_values($clean) : ['RILIS'];
    }

    /**
     * Menerapkan filter visibilitas (RILIS/PRIVATE) dan eksklusi Trash secara terpusat
     * pada query tabel `monthly_trade_summaries` untuk mencegah duplikasi logika.
     *
     * - Baris historis (`batch_id IS NULL`): mengikuti `status_visibilitas` (Keputusan A-1: PRIVATE).
     * - Baris ter-link (`batch_id IS NOT NULL`): wajib cocok pada `status_visibilitas` mart,
     *   `data_batches.status`, serta tidak sedang berada di `trash` (`restored_at IS NULL`).
     *
     * @param Builder $query
     * @param array<int, string> $allowedStatuses
     * @param string $alias
     * @return Builder
     */
    public function applyMonthlySummaryVisibility(
        Builder $query,
        array $allowedStatuses = ['RILIS'],
        string $alias = 'mts'
    ): Builder {
        $statuses = $this->sanitizeAllowedStatuses($allowedStatuses);

        return $query
            ->leftJoin('data_batches as b_vis', "{$alias}.batch_id", '=', 'b_vis.batch_id')
            ->whereIn("{$alias}.status_visibilitas", $statuses)
            ->where(function ($outer) use ($alias, $statuses) {
                $outer->whereNull("{$alias}.batch_id")
                    ->orWhere(function ($linked) use ($alias, $statuses) {
                        $linked->whereIn('b_vis.status', $statuses)
                            ->whereNotIn("{$alias}.batch_id", function ($sub) {
                                $sub->select('batch_id')->from('trash')->whereNull('restored_at');
                            });
                    });
            });
    }

    /**
     * Formula pertumbuhan resmi BPS menggunakan BCMath scale 8 lalu pembulatan akhir 2 desimal.
     * Mengembalikan null jika periode pembanding tidak tersedia atau penyebut <= 0 (Aturan 7).
     * Mengembalikan 0.0 hanya jika perubahan datar riil ($curr === $prev > 0).
     */
    public function calcBpsGrowth(?string $currVal, ?string $prevVal): ?float
    {
        if ($currVal === null || $prevVal === null) {
            return null;
        }

        if (bccomp($prevVal, '0', 6) <= 0) {
            return null;
        }

        $diff = bcsub($currVal, $prevVal, 6);
        $pct = bcdiv(bcmul($diff, '100', 8), $prevVal, 8);

        return round((float) $pct, 2);
    }

    /**
     * Menghitung ringkasan statistik resmi perdagangan luar negeri BPS Provinsi Riau.
     * Default hanya membaca data berstatus 'RILIS' dan selalu mengecualikan DRAFT & Trash.
     *
     * @param int|null $tahun Tahun periode (default: tahun terbaru yang sesuai izin visibilitas)
     * @param string $jenis 'Ekspor' atau 'Impor'
     * @param string $tipeSumber 'distribusi' atau 'neraca'
     * @param array<int, string> $allowedStatuses Daftar status diizinkan (default: ['RILIS'])
     * @return array<string, mixed>
     */
    public function getOfficialDashboardMetrics(
        ?int $tahun = null,
        string $jenis = 'Ekspor',
        string $tipeSumber = 'distribusi',
        array $allowedStatuses = ['RILIS']
    ): array {
        $tipeSumber = strtolower(trim($tipeSumber));
        if (!in_array($tipeSumber, ['distribusi', 'neraca'], true)) {
            $tipeSumber = 'distribusi';
        }

        $statuses = $this->sanitizeAllowedStatuses($allowedStatuses);

        // 1. Tentukan tahun acuan jika tidak disediakan (terfilter visibilitas & non-Trash)
        if (!$tahun) {
            $latestYearQuery = DB::table('monthly_trade_summaries as mts')
                ->where('mts.jenis_transaksi', $jenis)
                ->where('mts.tipe_sumber', $tipeSumber);
            $this->applyMonthlySummaryVisibility($latestYearQuery, $statuses, 'mts');
            $latestYear = $latestYearQuery->max('mts.tahun');

            if (!$latestYear) {
                // Fallback dinamis ke tabel transaksi sesuai sumber metodologi (Distribusi vs Neraca)
                $txTable = ($tipeSumber === 'neraca') ? 'neraca_transactions' : 'distribusi_transactions';
                $latestYear = DB::table("{$txTable} as t")
                    ->join('data_batches as b', 't.batch_id', '=', 'b.batch_id')
                    ->where('t.jenis_transaksi', $jenis)
                    ->where('t.tipe_sumber', $tipeSumber)
                    ->where('b.tipe_sumber', $tipeSumber)
                    ->whereIn('b.status', $statuses)
                    ->whereNotIn('t.batch_id', function ($sub) {
                        $sub->select('batch_id')->from('trash')->whereNull('restored_at');
                    })
                    ->max('t.tahun');
            }

            $tahun = $latestYear ? (int) $latestYear : (int) date('Y');
        }

        // 2. Ambil agregasi dari monthly_trade_summaries dengan isolasi visibilitas & non-Trash
        // Matriks cakupan Distribusi:
        // - Periode < 2026/07: Key 0 (historis tanpa pelabuhan, mengikuti Keputusan A-1 PRIVATE)
        // - Periode >= 2026/07: pelabuhan_key > 0 (DBF pabean resmi)
        $summariesQuery = DB::table('monthly_trade_summaries as mts')
            ->where('mts.jenis_transaksi', $jenis)
            ->where('mts.tipe_sumber', $tipeSumber)
            ->whereIn('mts.tahun', [$tahun, $tahun - 1]);

        $this->applyMonthlySummaryVisibility($summariesQuery, $statuses, 'mts');

        if ($tipeSumber === 'distribusi') {
            $summariesQuery->where(function ($q) {
                $q->where('mts.tahun', '<', 2026)
                    ->orWhere(function ($q2) {
                        $q2->where('mts.tahun', 2026)
                            ->where('mts.bulan', '<', 7);
                    })
                    ->orWhere('mts.pelabuhan_key', '>', 0);
            });
        }

        $summaries = $summariesQuery
            ->select(
                'mts.bulan',
                'mts.tahun',
                DB::raw('SUM(mts.total_nilai) as nilai'),
                DB::raw('SUM(mts.total_berat) as berat')
            )
            ->groupBy('mts.tahun', 'mts.bulan')
            ->get();

        // 3. Kelompokkan nilai per tahun & bulan dengan BCMath scale 6 + flag keberadaan bulan eksplisit
        $nilaiBulanCurrent = array_fill(1, 12, '0.000000');
        $beratBulanCurrent = array_fill(1, 12, '0.000000');
        $nilaiBulanPrev = array_fill(1, 12, '0.000000');
        $hasMonthCurrent = array_fill(1, 12, false);
        $hasMonthPrev = array_fill(1, 12, false);

        foreach ($summaries as $t) {
            $bln = (int) $t->bulan;
            if ($bln < 1 || $bln > 12) {
                continue;
            }

            $valStr = bcadd((string) ($t->nilai ?? '0'), '0', 6);
            $wgtStr = bcadd((string) ($t->berat ?? '0'), '0', 6);

            if ((int) $t->tahun === $tahun) {
                $nilaiBulanCurrent[$bln] = bcadd($nilaiBulanCurrent[$bln], $valStr, 6);
                $beratBulanCurrent[$bln] = bcadd($beratBulanCurrent[$bln], $wgtStr, 6);
                if (bccomp($nilaiBulanCurrent[$bln], '0', 6) > 0) {
                    $hasMonthCurrent[$bln] = true;
                }
            } elseif ((int) $t->tahun === ($tahun - 1)) {
                $nilaiBulanPrev[$bln] = bcadd($nilaiBulanPrev[$bln], $valStr, 6);
                if (bccomp($nilaiBulanPrev[$bln], '0', 6) > 0) {
                    $hasMonthPrev[$bln] = true;
                }
            }
        }

        $totalFobStr = '0.000000';
        $totalNettoStr = '0.000000';
        $latestMonth = 1;
        $hasData = false;

        for ($m = 1; $m <= 12; $m++) {
            $totalFobStr = bcadd($totalFobStr, $nilaiBulanCurrent[$m], 6);
            $totalNettoStr = bcadd($totalNettoStr, $beratBulanCurrent[$m], 6);
            if ($hasMonthCurrent[$m]) {
                $latestMonth = $m;
                $hasData = true;
            }
        }

        // Default seluruh pertumbuhan adalah null (unavailable / '—') bila tidak dapat dihitung valid
        $mtom = null;
        $qtoq = null;
        $yony = null;
        $ctoc = null;

        if ($hasData) {
            $valLatest = $nilaiBulanCurrent[$latestMonth];

            // 4. M-to-M: Bulan $latestMonth vs bulan tepat sebelumnya ($latestMonth - 1, wrap Jan -> Des Y-1)
            $prevMonth = ($latestMonth === 1) ? 12 : ($latestMonth - 1);
            $hasPrevM = ($latestMonth === 1) ? $hasMonthPrev[12] : $hasMonthCurrent[$prevMonth];
            $valPrevM = ($latestMonth === 1) ? $nilaiBulanPrev[12] : $nilaiBulanCurrent[$prevMonth];
            if ($hasPrevM) {
                $mtom = $this->calcBpsGrowth($valLatest, $valPrevM);
            }

            // 5. Q-to-Q (Kebijakan B): Wajib kelengkapan 3 bulan penuh pada KEDUA triwulan
            // ($currentQuarter vs $prevQuarter), termasuk menolak celah bulan internal; tanpa fallback.
            $currentQuarter = (int) ceil($latestMonth / 3);
            $currQStart = ($currentQuarter - 1) * 3 + 1;
            $currQEnd = $currentQuarter * 3;
            $currQComplete = true;
            $qCurrentSum = '0.000000';

            for ($m = $currQStart; $m <= $currQEnd; $m++) {
                if (!$hasMonthCurrent[$m]) {
                    $currQComplete = false;
                    break;
                }
                $qCurrentSum = bcadd($qCurrentSum, $nilaiBulanCurrent[$m], 6);
            }

            $prevQuarter = ($currentQuarter === 1) ? 4 : ($currentQuarter - 1);
            $prevQStart = ($prevQuarter - 1) * 3 + 1;
            $prevQEnd = $prevQuarter * 3;
            $prevQComplete = true;
            $qPrevSum = '0.000000';

            for ($m = $prevQStart; $m <= $prevQEnd; $m++) {
                $hasM = ($currentQuarter === 1) ? $hasMonthPrev[$m] : $hasMonthCurrent[$m];
                $valM = ($currentQuarter === 1) ? $nilaiBulanPrev[$m] : $nilaiBulanCurrent[$m];
                if (!$hasM) {
                    $prevQComplete = false;
                    break;
                }
                $qPrevSum = bcadd($qPrevSum, $valM, 6);
            }

            if ($currQComplete && $prevQComplete) {
                $qtoq = $this->calcBpsGrowth($qCurrentSum, $qPrevSum);
            }

            // 6. Y-on-Y: Bulan $latestMonth tahun ini vs bulan sama tahun lalu
            if ($hasMonthPrev[$latestMonth]) {
                $yony = $this->calcBpsGrowth($valLatest, $nilaiBulanPrev[$latestMonth]);
            }

            // 7. C-to-C: Kumulatif Jan–$latestMonth tahun ini vs Jan–$latestMonth tahun lalu
            $cumCurrent = '0.000000';
            $cumPrev = '0.000000';
            $hasAnyPrevCum = false;

            for ($m = 1; $m <= $latestMonth; $m++) {
                $cumCurrent = bcadd($cumCurrent, $nilaiBulanCurrent[$m], 6);
                if ($hasMonthPrev[$m]) {
                    $cumPrev = bcadd($cumPrev, $nilaiBulanPrev[$m], 6);
                    $hasAnyPrevCum = true;
                }
            }

            if ($hasAnyPrevCum) {
                $ctoc = $this->calcBpsGrowth($cumCurrent, $cumPrev);
            }
        }

        // 8. Bentuk Tren Bulanan 12 Bulan (Januari–Desember)
        $namaBulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $monthlyTrend = [];
        for ($m = 1; $m <= 12; $m++) {
            $mVal = (float) $nilaiBulanCurrent[$m];
            $mWgt = (float) $beratBulanCurrent[$m];
            $monthlyTrend[] = [
                'bulan' => $m,
                'nama_bulan' => $namaBulan[$m],
                'nilai' => $mVal,
                'nilai_juta_usd' => round((float) bcdiv($nilaiBulanCurrent[$m], '1000000', 8), 2),
                'berat' => $mWgt,
            ];
        }

        $totalFob = (float) $totalFobStr;
        $totalNetto = (float) $totalNettoStr;

        return [
            'has_data' => $hasData,
            'tahun' => $tahun,
            'bulan_terakhir' => $latestMonth,
            'nama_bulan_terakhir' => $namaBulan[$latestMonth],
            'jenis' => $jenis,
            'total_fob' => $totalFob,
            'total_fob_juta_usd' => round((float) bcdiv($totalFobStr, '1000000', 8), 2),
            'total_netto' => $totalNetto,
            'total_netto_ribu_ton' => round((float) bcdiv($totalNettoStr, '1000000', 8), 2),
            'mtom' => $mtom,
            'qtoq' => $qtoq,
            'yony' => $yony,
            'ctoc' => $ctoc,
            'monthly_trend' => $monthlyTrend,
        ];
    }
}
