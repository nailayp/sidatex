# LAPORAN PENILAIAN KESELAMATAN BATCH #74 (PHASE 2G.10.3)
## SISTEM INFORMASI DATA EKSPOR-IMPOR (SIDATEX)
### BADAN PUSAT STATISTIK (BPS) PROVINSI RIAU

**Kode Dokumen:** `SIDATEX-REP-BATCH74-SAFETY-2G10-3`  
**Tanggal Audit:** 19 November 2026  
**Auditor / Reviewer:** Senior QA Documentation Engineer, Database Guardian, & Statistics Auditor  
**Mode Pelaksanaan:** 100% STRICTLY READ-ONLY ASSESSMENT (Nol mutasi basis data, nol perubahan kode aplikasi, nol modifikasi dokumen katalog test case)  
**Database Target:** `sidatex_testing` (MySQL / MariaDB terisolasi)  
**Status Kelayakan Batch #74:** `NOT SAFE FOR UAT`  
**Rekomendasi Status TC-UAT-SA-03:** `BLOCKED — WAITING FOR APPROVED TEST BATCH`

---

## 1. RINGKASAN PEMERIKSAAN

Pemeriksaan teknis mendalam (*deep safety assessment*) telah dilakukan secara mandiri dan read-only terhadap usulan penggunaan **Batch #74** sebagai objek pengujian pengalihan status rilis (*Toggle Release*) pada skenario `TC-UAT-SA-03`.

### Temuan Kunci Pemeriksaan:
1. **Batch #74 BUKAN Batch Uji Coba Pementasan (Bukan Disposable / Staging Batch):**  
   Batch #74 adalah dataset riil **Impor Distribusi Provinsi Riau Periode September 2026** yang memuat **931 baris transaksi**, nilai moneter **US\$ 215.260.644,472700 (US\$ 215,26 Juta)**, dan berat bersih **578.149.534,851500 Kg (578,15 Ribu Ton)**.
2. **Keterkaitan Inti dengan Agregasi Data Mart & Laporan BRS:**  
   Batch #74 telah diagregasi penuh ke dalam tabel analitis:
   - `monthly_trade_summaries`: **918 baris agregasi**
   - `brs_indicator_summaries`: **38 baris indikator makro BRS**
3. **Ketergantungan Kritis Baseline Pengujian Otomatis (PHPUnit Regression Anchor):**  
   Status `PRIVATE` pada Batch #74 merupakan **kondisi jangkar (*anchor condition*)** yang mendasari dua pengujian otomatis krusial:
   - **`tests/Feature/Phase2gDistributionGrowthAuditTest.php` (Baris 95–115):** Memvalidasi bahwa karena Batch #74 berstatus `PRIVATE`, Pegawai melihat data impor September 2026 kosong (`has_data = false`) dan pertumbuhan MoM Impor bernilai `null` (strip `—`), bukan angka palsu `-100%` (fondasi Phase 2G.5).
   - **`tests/Feature/Phase2g8ControlledRemediationTest.php` (Baris 30–50):** Memvalidasi fitur peringatan asimetri Neraca Perdagangan kumulatif F-03 (`#alertTradeAsymmetry`), di mana Ekspor RILIS tersedia sampai September (Batch #71) sedangkan Impor RILIS hanya tersedia sampai Agustus (Batch #70), sehingga `is_asymmetric_cumulative` wajib bernilai `TRUE` untuk Pegawai.
4. **Dampak Fatal jika Batch #74 Di-Toggle ke RILIS:**  
   Jika status Batch #74 diubah menjadi `RILIS`:
   - Data Impor September 2026 akan seketika muncul di dashboard Pegawai.
   - Peringatan asimetri Trade Balance Tab 4 (`#alertTradeAsymmetry`) akan hilang karena rentang bulan ekspor dan impor menjadi simetris (keduanya Jan–Sep). Hal ini menyebabkan skenario manual **`TC-UAT-PG-06` (Verifikasi Alert Asimetri F-03) GAGAL / FAIL**.
   - Skenario manual **`TC-UAT-PG-08` (Verifikasi Pertumbuhan Data Kosong)** pada Impor September 2026 akan **GAGAL / FAIL**.
   - Pengujian otomatis PHPUnit `Phase2gDistributionGrowthAuditTest` dan `Phase2g8ControlledRemediationTest` akan **RUSAK / FAIL**.

---

## 2. BUKTI QUERY DAN HASIL PEMERIKSAAN

Seluruh data di bawah diperoleh melalui query `SELECT` read-only pada koneksi `sidatex_testing`:

### 2.1 Metadata Batch pada Tabel `data_batches`
```sql
SELECT batch_id, nama_file, jenis_data, periode_tahun, periode_bulan, tipe_sumber,
       ukuran_file, status, uploaded_by, uploaded_at, released_by, released_at,
       created_at, updated_at
FROM data_batches
WHERE batch_id = 74;
```
**Hasil Query:**
```json
{
    "batch_id": 74,
    "nama_file": "1794547156_14_impor_atap_kdprov_0926.DBF",
    "jenis_data": "Impor",
    "periode_tahun": "2026",
    "periode_bulan": 9,
    "tipe_sumber": "distribusi",
    "ukuran_file": "0.18",
    "status": "PRIVATE",
    "uploaded_by": 1,
    "uploaded_at": "2026-11-12 16:20:27",
    "released_by": null,
    "released_at": null,
    "created_at": "2026-11-12 16:20:27",
    "updated_at": "2026-11-13 05:19:16"
}
```

### 2.2 Volume Transaksi pada Tabel `distribusi_transactions`
```sql
SELECT count(*) AS total_transaksi,
       sum(nilai) AS total_nilai_usd,
       sum(berat) AS total_berat_kg
FROM distribusi_transactions
WHERE batch_id = 74;
```
**Hasil Query:**
- **Total Transaksi:** `931` baris
- **Total Nilai Moneter:** `US$ 215.260.644,472700`
- **Total Berat Bersih:** `578.149.534,851500` Kg

### 2.3 Keterkaitan Agregasi pada Analytical Data Mart Tier
```sql
SELECT count(*) AS total_monthly_summaries 
FROM monthly_trade_summaries 
WHERE batch_id = 74;
-- Hasil: 918 baris agregasi

SELECT count(*) AS total_brs_indicators 
FROM brs_indicator_summaries 
WHERE batch_id = 74;
-- Hasil: 38 baris indikator makro
```

### 2.4 Bukti Ketergantungan Kode Uji Otomatis PHPUnit

#### A. Bukti dari `tests/Feature/Phase2gDistributionGrowthAuditTest.php` (Baris 95–106):
```php
/**
 * 1. Payload LIVE sidatex_testing (Role 2 Pegawai):
 * - 2026-09 Impor (Batch #74 masih PRIVATE): mtom_total/nonmigas/migas HARUS null (bukan -100!).
 * - 2026-10 Ekspor (belum ada batch): mtom_total/nonmigas/migas HARUS null & top_commodities.items === [].
 * - 2026-08 Ekspor (Batch #720 masih PRIVATE): top_commodities.items === [] (tanpa 'Lainnya' semu).
 * - 2026-09 Ekspor (Batch #71 RILIS): top_commodities.items tetap 4 item (Top 3 + Lainnya).
 */
public function test_live_missing_periods_return_null_instead_of_fake_minus_100_and_no_phantom_lainnya(): void
{
    $pegawai = User::where('role_id', 2)->firstOrFail();

    // 2026-09: Ekspor RILIS (#71), Impor PRIVATE (#74)
    $resSep = $this->actingAs($pegawai)->getJson(route('distribusi.metrics', ['tahun' => 2026, 'bulan' => 9]));
    $resSep->assertStatus(200);
    $this->assertFalse($resSep->json('impor.bulan_ini.has_data'));
    $this->assertNull($resSep->json('impor.pertumbuhan.mtom_total'));
...
```

#### B. Bukti dari `tests/Feature/Phase2g8ControlledRemediationTest.php` (Baris 38–48):
```php
    // 2. Live Pegawai (Role 2: HANYA melihat data RILIS)
    $tbPegawai = $resPegawai['trade_balance'];
    $this->assertArrayHasKey('is_asymmetric_cumulative', $tbPegawai);
    $this->assertSame(9, $tbPegawai['max_ekspor_bulan']);
    $this->assertSame('September', $tbPegawai['max_ekspor_nama_bulan']);
    $this->assertSame('Agustus', $tbPegawai['max_impor_nama_bulan']);
    $this->assertTrue($tbPegawai['is_asymmetric_cumulative'], 
        'Pegawai melihat data RILIS: Ekspor (Sep) vs Impor (Ags) wajib terdeteksi asimetris!');
```

---

## 3. ANALISIS RISIKO JIKA BATCH #74 DIGUNAKAN

| Dimensi Risiko | Deskripsi Dampak Teknis & Metodologis | Tingkat Risiko |
|---|---|:---:|
| **Kegagalan Skenario UAT `TC-UAT-PG-06` (F-03)** | Jika Batch #74 diubah ke `RILIS`, data Impor Pegawai memiliki rentang hingga September (Bulan 9). Rentang bulan Ekspor (Jan–Sep) dan Impor (Jan–Sep) menjadi simetris sempurna. Akibatnya, banner peringatan amber asimetri (`#alertTradeAsymmetry`) **tidak akan muncul**, dan skenario pengujian `TC-UAT-PG-06` akan **FAIL**. | **KRITIS (High)** |
| **Kegagalan Skenario UAT `TC-UAT-PG-08` (Pertumbuhan)** | Jika Batch #74 berstatus `RILIS`, Impor September 2026 memiliki data riil US\$ 215,26 Juta. Pertumbuhan MoM Impor akan berubah menjadi `+28,63%`, menghilangkan kondisi uji data kosong bernilai `null` (`—`) bagi Pegawai. | **TINGGI (High)** |
| **Kerusakan Test Suite Otomatis PHPUnit** | 2 berkas tes fitur (`Phase2gDistributionGrowthAuditTest` dan `Phase2g8ControlledRemediationTest`) secara eksplisit memeriksa kondisi runtime di mana Batch #74 berstatus `PRIVATE`. Jika statusnya berubah, tes otomatis akan seketika gagal (*PHPUnit Failure*). | **KRITIS (High)** |
| **Kontaminasi Data Mart Publik September 2026** | Mengubah Batch #74 menjadi `RILIS` menambahkan US\$ 215,26 Juta data impor ke neraca perdagangan resmi publik September 2026 pada database testing, mengubah surplus/defisit bersih yang selama ini menjadi baseline audit. | **TINGGI (High)** |

---

## 4. KESIMPULAN KELAYAKAN

Berdasarkan seluruh bukti struktural basis data, keterkaitan data mart analitis, dan dependensi test suite PHPUnit:

$$\mathbf{STATUS\ KELAYAKAN:\ NOT\ SAFE\ FOR\ UAT}$$

> [!CAUTION]
> **Batch #74 TIDAK AMAN DAN TIDAK BOLEH DIGUNAKAN** sebagai objek pengujian pengalihan status (*Toggle Release*) pada `TC-UAT-SA-03`. Batch #74 merupakan bagian integral dari baseline pengujian fungsional F-03 (asimetri neraca) dan F-05/Phase 2G.5 (integritas pertumbuhan data impor kosong).

---

## 5. REKOMENDASI TINDAKAN BERIKUTNYA

Sesuai instruksi dan batasan pengguna:
1. **Status Rekomendasi Skenario `TC-UAT-SA-03`:**  
   Skenario `TC-UAT-SA-03` pada pelaksanaan Manual UAT lapangan direkomendasikan untuk ditandai sebagai:  
   $$\mathbf{BLOCKED\ —\ WAITING\ FOR\ APPROVED\ TEST\ BATCH}$$  
   Penguji Super Admin tidak boleh menjalankan aksi *Toggle Release* pada Batch #74, Batch #71, maupun Batch #720.
2. **Katalog Test Case Tidak Diubah:**  
   Sesuai batasan eksplisit pengguna, dokumen `SIDATEX_PHASE_2G_10_MANUAL_UAT_TEST_CASES.md` **TIDAK DIUBAH** secara sepihak. Catatan pemblokiran ini didokumentasikan secara formal dalam laporan penilaian keselamatan ini.
3. **Penyediaan Batch Uji Sintetis Khusus oleh BPS:**  
   Jika BPS Provinsi Riau menghendaki pengujian fungsional *Toggle Release* secara nyata saat sesi UAT lapangan, teknisi BPS dapat membuat 1 batch uji sintetis baru (*disposable dummy batch*) di luar periode September 2026 atau mengunggah file uji terisolasi.

---

## 6. STOP CONDITION

Pemeriksaan keselamatan Batch #74 telah selesai dilaksanakan secara 100% read-only tanpa menyentuh database operasional `sidatex` maupun mengubah kode aplikasi.

**PEKERJAAN DIHENTIKAN DI SINI SESUAI INSTRUKSI. TIDAK ADA TINDAKAN LANJUTAN KE PHASE 2G.11 TANPA PERSETUJUAN TERTULIS DARI PENGGUNA.**
