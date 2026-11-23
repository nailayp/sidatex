<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model DistribusiTransaction
 * Merepresentasikan data transaksi fisik khusus modul Distribusi pada tabel `distribusi_transactions`.
 */
class DistribusiTransaction extends Model
{
    // Nama tabel fisik khusus Distribusi
    protected $table = 'distribusi_transactions';

    // Primary key tabel
    protected $primaryKey = 'transaction_id';

    // Kolom yang dapat diisi secara mass-assignment
    protected $fillable = [
        'batch_id',
        'hs_id',
        'negara_id',
        'provinsi_id',
        'kabkota_id',
        'provorig',
        'pelabuhan_id',
        'jenis_transaksi',
        'tipe_sumber',
        'bulan',
        'tahun',
        'berat',
        'nilai',
    ];

    /**
     * Relasi ke batch unggahan (data_batches).
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(DataBatch::class, 'batch_id', 'batch_id');
    }

    /**
     * Relasi ke master kode HS / komoditas.
     */
    public function masterHs(): BelongsTo
    {
        return $this->belongsTo(MasterHs::class, 'hs_id', 'hs_id');
    }

    /**
     * Relasi ke master negara tujuan/asal.
     */
    public function negara(): BelongsTo
    {
        return $this->belongsTo(MasterNegara::class, 'negara_id', 'negara_id');
    }

    /**
     * Relasi ke master provinsi.
     */
    public function provinsi(): BelongsTo
    {
        return $this->belongsTo(MasterProvinsi::class, 'provinsi_id', 'provinsi_id');
    }

    /**
     * Relasi ke master kabupaten/kota asal muat.
     */
    public function kabkota(): BelongsTo
    {
        return $this->belongsTo(MasterKabkota::class, 'kabkota_id', 'kabkota_id');
    }

    /**
     * Relasi ke master pelabuhan muat/bongkar.
     */
    public function pelabuhan(): BelongsTo
    {
        return $this->belongsTo(MasterPelabuhan::class, 'pelabuhan_id', 'pelabuhan_id');
    }
}
