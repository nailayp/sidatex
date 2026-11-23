<?php

namespace App\Services;

use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\Style\Color;
use PhpOffice\PhpPresentation\Style\Alignment;
use Illuminate\Support\Facades\DB;

class PowerPointExportService
{
    /**
     * Menghasilkan file presentasi PowerPoint (.pptx) bahan rilis BRS BPS Riau.
     *
     * @param int $batchId ID batch dokumen
     * @return string Path file sementara .pptx yang dihasilkan
     */
    public function generateBrsSlideDeck(int $batchId): string
    {
        $batch = DB::table('data_batches')->where('batch_id', $batchId)->first();
        if (!$batch) {
            throw new \Exception("Batch #{$batchId} tidak ditemukan.");
        }

        $jenis = $batch->jenis_data ?? 'Ekspor';
        $tipeSumber = strtolower(trim((string) ($batch->tipe_sumber ?? 'distribusi')));
        $txTable = ($tipeSumber === 'neraca') ? 'neraca_transactions' : 'distribusi_transactions';
        $isImpor = strtolower(trim((string) $jenis)) === 'impor';
        $nilaiLabel = $isImpor ? 'TOTAL NILAI CIF' : 'TOTAL NILAI FOB';

        // Ambil transaksi agregat dari tabel transaksi sesuai metodologi batch
        $transaksi = DB::table($txTable)
            ->where('batch_id', $batchId)
            ->select(
                DB::raw('COUNT(*) as total_transaksi'),
                DB::raw('COALESCE(SUM(nilai), 0) as total_fob'),
                DB::raw('COALESCE(SUM(berat), 0) as total_netto'),
                DB::raw('MIN(bulan) as min_bulan'),
                DB::raw('MAX(bulan) as max_bulan'),
                DB::raw('MIN(tahun) as tahun')
            )
            ->first();

        $totalNilaiStr = bcadd((string) ($transaksi->total_fob ?? '0'), '0', 6);
        $totalNettoStr = bcadd((string) ($transaksi->total_netto ?? '0'), '0', 6);
        $totalFobJuta = round((float) bcdiv($totalNilaiStr, '1000000', 6), 2);
        $totalNettoRibuTon = round((float) bcdiv($totalNettoStr, '1000000', 6), 2);
        $tahun = $transaksi->tahun ?? $batch->periode_tahun ?? date('Y');

        $objPHPPowerPoint = new PhpPresentation();

        // -------------------------------------------------------------
        // SLIDE 1: JUDUL RESMI BRS BPS PROVINSI RIAU
        // -------------------------------------------------------------
        $currentSlide = $objPHPPowerPoint->getActiveSlide();

        // Background Slide 1 (Biru BPS)
        $shape = $currentSlide->createRichTextShape()
            ->setHeight(120)
            ->setWidth(850)
            ->setOffsetX(50)
            ->setOffsetY(60);
        $shape->getActiveParagraph()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $textRun = $shape->createTextRun("BERITA RESMI STATISTIK\nBPS PROVINSI RIAU");
        $textRun->getFont()->setBold(true)
            ->setSize(28)
            ->setColor(new Color('FF1E3A8A')); // Dark Blue

        $subShape = $currentSlide->createRichTextShape()
            ->setHeight(100)
            ->setWidth(850)
            ->setOffsetX(50)
            ->setOffsetY(200);

        $scopeLabel = ($tipeSumber === 'neraca')
            ? 'Metodologi Neraca (PROVORIG / KPPBC Riau)'
            : 'Metodologi Distribusi (Pelabuhan Muat / Bongkar Riau)';
        $minBulan = $transaksi->min_bulan ?? $batch->periode_bulan;
        $maxBulan = $transaksi->max_bulan ?? $batch->periode_bulan;
        $periodeLabel = ($minBulan && $maxBulan && $minBulan !== $maxBulan)
            ? "Bulan {$minBulan}–{$maxBulan} Tahun {$tahun}"
            : ($minBulan ? "Bulan {$minBulan} Tahun {$tahun}" : "Tahun {$tahun}");

        $subRun = $subShape->createTextRun("Perkembangan {$jenis} Provinsi Riau\n{$scopeLabel}\nPeriode: {$periodeLabel}\nDokumen: {$batch->nama_file}");
        $subRun->getFont()->setSize(20)->setColor(new Color('FF475569'));

        // -------------------------------------------------------------
        // SLIDE 2: RINGKASAN PERDAGANGAN & INDIKATOR UTAMA
        // -------------------------------------------------------------
        $slide2 = $objPHPPowerPoint->createSlide();

        $titleShape = $slide2->createRichTextShape()
            ->setHeight(60)
            ->setWidth(850)
            ->setOffsetX(50)
            ->setOffsetY(40);
        $titleRun = $titleShape->createTextRun("Ringkasan Statistik {$jenis} Provinsi Riau");
        $titleRun->getFont()->setBold(true)->setSize(22)->setColor(new Color('FF1E3A8A'));

        // Kotak Metrik 1: Total Nilai FOB/CIF
        $boxFob = $slide2->createRichTextShape()
            ->setHeight(180)
            ->setWidth(380)
            ->setOffsetX(50)
            ->setOffsetY(130);
        $boxFobRun = $boxFob->createTextRun("{$nilaiLabel}\n\nUS$ {$totalFobJuta} Juta\n\nTotal Transaksi: " . number_format($transaksi->total_transaksi ?? 0, 0, ',', '.'));
        $boxFobRun->getFont()->setBold(true)->setSize(18)->setColor(new Color('FF0284C7'));

        // Kotak Metrik 2: Total Berat Netto
        $boxNetto = $slide2->createRichTextShape()
            ->setHeight(180)
            ->setWidth(380)
            ->setOffsetX(470)
            ->setOffsetY(130);
        $boxNettoRun = $boxNetto->createTextRun("TOTAL BERAT BERSIH\n\n{$totalNettoRibuTon} Ribu Ton\n\nStatus Dokumen: {$batch->status}");
        $boxNettoRun->getFont()->setBold(true)->setSize(18)->setColor(new Color('FF16A34A'));

        // Simpan ke file temporary
        $oWriter = IOFactory::createWriter($objPHPPowerPoint, 'PowerPoint2007');
        $tempPath = tempnam(sys_get_temp_dir(), 'SIDATEX_BRS_') . '.pptx';
        $oWriter->save($tempPath);

        return $tempPath;
    }
}
