<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    // Nama tabel yang digunakan model.
    protected $table = 'transactions';

    // Primary key tabel transaksi.
    protected $primaryKey = 'transaction_id';

    // Kolom yang boleh diisi saat menyimpan transaksi.
    protected $fillable = [
        'batch_id',
        'hs_id',
        'negara_id',
        'provinsi_id',
        'kabkota_id',
        'pelabuhan_id',
        'jenis_transaksi',
        'bulan',
        'tahun',
        'berat',
        'nilai',
    ];

    /**
     * Setiap transaksi berasal dari satu batch unggahan.
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(DataBatch::class, 'batch_id', 'batch_id');
    }

    /**
     * Relasi ke master kode HS/komoditas.
     */
    public function masterHs(): BelongsTo
    {
        return $this->belongsTo(MasterHs::class, 'hs_id', 'hs_id');
    }

    /**
     * Relasi ke master negara tujuan.
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
     * Relasi ke master kabupaten/kota.
     */
    public function kabkota(): BelongsTo
    {
        return $this->belongsTo(MasterKabkota::class, 'kabkota_id', 'kabkota_id');
    }

    /**
     * Relasi ke master pelabuhan.
     */
    public function pelabuhan(): BelongsTo
    {
        return $this->belongsTo(MasterPelabuhan::class, 'pelabuhan_id', 'pelabuhan_id');
    }
}
