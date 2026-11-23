<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrsIndicatorSummary extends Model
{
    /**
     * Nama tabel di database.
     */
    protected $table = 'brs_indicator_summaries';

    /**
     * Atribut yang dapat diisi secara massal.
     */
    protected $fillable = [
        'batch_id',
        'status_visibilitas',
        'tipe_sumber',
        'jenis_transaksi',
        'tahun',
        'bulan',
        'periode_tipe',
        'kategori_dimensi',
        'kode_dimensi',
        'nama_dimensi',
        'nilai_usd',
        'nilai_juta_usd',
        'volume_ribu_ton',
        'share_percentage',
        'growth_mtom',
        'growth_yoy',
        'growth_ctc',
    ];

    /**
     * Casts tipe data atribut.
     */
    protected $casts = [
        'batch_id' => 'integer',
        'tahun' => 'integer',
        'bulan' => 'integer',
        'nilai_usd' => 'string',
        'nilai_juta_usd' => 'string',
        'volume_ribu_ton' => 'string',
        'share_percentage' => 'float',
        'growth_mtom' => 'float',
        'growth_yoy' => 'float',
        'growth_ctc' => 'float',
    ];

    /**
     * Relasi ke Data Batch.
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(DataBatch::class, 'batch_id', 'batch_id');
    }
}
