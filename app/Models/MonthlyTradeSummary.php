<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyTradeSummary extends Model
{
    /**
     * Nama tabel di database.
     */
    protected $table = 'monthly_trade_summaries';

    /**
     * Primary key tabel agregasi.
     */
    protected $primaryKey = 'summary_id';

    /**
     * Atribut yang dapat diisi secara massal.
     */
    protected $fillable = [
        'batch_id',
        'status_visibilitas',
        'tipe_sumber',
        'hs_id',
        'hs2',
        'hs_code_raw',
        'mapping_status',
        'is_migas',
        'sektor',
        'bec_category',
        'provorig_key',
        'negara_id',
        'provinsi_id',
        'pelabuhan_id',
        'pelabuhan_key',
        'jenis_transaksi',
        'bulan',
        'tahun',
        'total_berat',
        'total_nilai',
        'jumlah_transaksi',
    ];

    /**
     * Casts tipe data atribut.
     */
    protected $casts = [
        'batch_id' => 'integer',
        'is_migas' => 'boolean',
        'pelabuhan_key' => 'integer',
        'bulan' => 'integer',
        'tahun' => 'integer',
        'total_berat' => 'string',
        'total_nilai' => 'string',
        'jumlah_transaksi' => 'integer',
    ];

    /**
     * Relasi ke Data Batch.
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(DataBatch::class, 'batch_id', 'batch_id');
    }

    /**
     * Relasi ke Master HS.
     */
    public function hs(): BelongsTo
    {
        return $this->belongsTo(MasterHs::class, 'hs_id', 'hs_id');
    }

    /**
     * Relasi ke Master Negara.
     */
    public function negara(): BelongsTo
    {
        return $this->belongsTo(MasterNegara::class, 'negara_id', 'negara_id');
    }

    /**
     * Relasi ke Master Provinsi.
     */
    public function provinsi(): BelongsTo
    {
        return $this->belongsTo(MasterProvinsi::class, 'provinsi_id', 'provinsi_id');
    }

    /**
     * Relasi ke Master Pelabuhan.
     */
    public function pelabuhan(): BelongsTo
    {
        return $this->belongsTo(MasterPelabuhan::class, 'pelabuhan_id', 'pelabuhan_id');
    }
}
