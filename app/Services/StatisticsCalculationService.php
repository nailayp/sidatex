<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class StatisticsCalculationService
{
    /**
     * Menghitung ringkasan statistik resmi perdagangan luar negeri BPS Provinsi Riau.
     * HANYA menghitung data transaksi yang berasal dari batch berstatus 'RILIS'.
     *
     * @param int|null $tahun Tahun periode (default: tahun transaksi terbaru yang dirilis)
     * @param string $jenis 'Ekspor' atau 'Impor'
     * @return array
     */
    public function getOfficialDashboardMetrics(?int $tahun = null, string $jenis = 'Ekspor'): array
    {
        // 1. Tentukan tahun acuan jika tidak disediakan
        if (!$tahun) {
            $latestYear = DB::table('transactions')
                ->join('data_batches', 'transactions.batch_id', '=', 'data_batches.batch_id')
                ->where('data_batches.status', 'RILIS')
                ->where('transactions.jenis_transaksi', $jenis)
                ->max('transactions.tahun');

            $tahun = $latestYear ?? (int) date('Y');
        }

        // 2. Ambil seluruh transaksi berstatus RILIS untuk tahun ini dan tahun sebelumnya
        $transactions = DB::table('transactions')
            ->join('data_batches', 'transactions.batch_id', '=', 'data_batches.batch_id')
            ->where('data_batches.status', 'RILIS')
            ->where('transactions.jenis_transaksi', $jenis)
            ->whereIn('transactions.tahun', [$tahun, $tahun - 1])
            ->select('transactions.bulan', 'transactions.tahun', 'transactions.nilai', 'transactions.berat')
            ->get();

        // 3. Kelompokkan nilai per tahun dan bulan
        $nilaiBulanCurrent = array_fill(1, 12, 0.0);
        $beratBulanCurrent = array_fill(1, 12, 0.0);
        $nilaiBulanPrev = array_fill(1, 12, 0.0);

        foreach ($transactions as $t) {
            $bln = (int) $t->bulan;
            if ($bln < 1 || $bln > 12) continue;

            $val = (float) $t->nilai;
            $wgt = (float) $t->berat;

            if ((int) $t->tahun === $tahun) {
                $nilaiBulanCurrent[$bln] += $val;
                $beratBulanCurrent[$bln] += $wgt;
            } elseif ((int) $t->tahun === ($tahun - 1)) {
                $nilaiBulanPrev[$bln] += $val;
            }
        }

        $totalFob = array_sum($nilaiBulanCurrent);
        $totalNetto = array_sum($beratBulanCurrent);

        // Cari bulan terakhir yang memiliki transaksi di tahun berjalan
        $latestMonth = 1;
        for ($m = 12; $m >= 1; $m--) {
            if ($nilaiBulanCurrent[$m] > 0) {
                $latestMonth = $m;
                break;
            }
        }

        // 4. Hitung M-to-M (Bulan $latestMonth vs $latestMonth - 1)
        $mtom = 0.0;
        $valLatest = $nilaiBulanCurrent[$latestMonth];
        $prevMonth = $latestMonth - 1;
        $valPrevM = ($prevMonth >= 1) ? $nilaiBulanCurrent[$prevMonth] : ($nilaiBulanPrev[12] ?? 0);
        if ($valPrevM > 0) {
            $mtom = round((($valLatest - $valPrevM) / $valPrevM) * 100, 2);
        }

        // 5. Hitung Q-to-Q (Kuartal terkini vs Kuartal sebelumnya)
        $currentQuarter = (int) ceil($latestMonth / 3);
        $qCurrentSum = 0.0;
        $qPrevSum = 0.0;

        for ($m = ($currentQuarter - 1) * 3 + 1; $m <= min($currentQuarter * 3, $latestMonth); $m++) {
            $qCurrentSum += $nilaiBulanCurrent[$m];
        }

        if ($currentQuarter > 1) {
            for ($m = ($currentQuarter - 2) * 3 + 1; $m <= ($currentQuarter - 1) * 3; $m++) {
                $qPrevSum += $nilaiBulanCurrent[$m];
            }
        } else {
            // Kuartal 4 tahun lalu (Okt, Nov, Des)
            $qPrevSum = $nilaiBulanPrev[10] + $nilaiBulanPrev[11] + $nilaiBulanPrev[12];
        }

        $qtoq = ($qPrevSum > 0) ? round((($qCurrentSum - $qPrevSum) / $qPrevSum) * 100, 2) : 0.0;

        // 6. Hitung Y-on-Y (Bulan $latestMonth tahun ini vs bulan yang sama tahun lalu)
        $valSameMonthLastYear = $nilaiBulanPrev[$latestMonth] ?? 0;
        $yony = ($valSameMonthLastYear > 0) ? round((($valLatest - $valSameMonthLastYear) / $valSameMonthLastYear) * 100, 2) : 0.0;

        // 7. Hitung C-to-C (Kumulatif Jan-$latestMonth tahun ini vs tahun lalu)
        $cumCurrent = 0.0;
        $cumPrev = 0.0;
        for ($m = 1; $m <= $latestMonth; $m++) {
            $cumCurrent += $nilaiBulanCurrent[$m];
            $cumPrev += $nilaiBulanPrev[$m] ?? 0;
        }
        $ctoc = ($cumPrev > 0) ? round((($cumCurrent - $cumPrev) / $cumPrev) * 100, 2) : 0.0;

        // 8. Bentuk Tren Bulanan 12 Bulan (Januari–Desember)
        $namaBulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $monthlyTrend = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthlyTrend[] = [
                'bulan' => $m,
                'nama_bulan' => $namaBulan[$m],
                'nilai' => $nilaiBulanCurrent[$m],
                'nilai_juta_usd' => round($nilaiBulanCurrent[$m] / 1000000, 2),
                'berat' => $beratBulanCurrent[$m],
            ];
        }

        return [
            'has_data' => ($totalFob > 0),
            'tahun' => $tahun,
            'bulan_terakhir' => $latestMonth,
            'nama_bulan_terakhir' => $namaBulan[$latestMonth],
            'jenis' => $jenis,
            'total_fob' => $totalFob,
            'total_fob_juta_usd' => round($totalFob / 1000000, 2),
            'total_netto' => $totalNetto,
            'total_netto_ribu_ton' => round($totalNetto / 1000000, 2),
            'mtom' => $mtom,
            'qtoq' => $qtoq,
            'yony' => $yony,
            'ctoc' => $ctoc,
            'monthly_trend' => $monthlyTrend,
        ];
    }
}
