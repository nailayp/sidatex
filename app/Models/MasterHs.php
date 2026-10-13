<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MasterHs extends Model
{
    // Tabel referensi kode HS untuk komoditas ekspor-impor.
    protected $table = 'master_hs';
    protected $primaryKey = 'hs_id';

    // Tabel master_hs tidak menggunakan created_at dan updated_at.
    public $timestamps = false;

    protected $fillable = [
        'hs_code',
        'hs2',
        'hs2_desc',
        'bec_code',
        'bec_desc',
        'sektor',
        'commgrp_code',
        'commgrp_desc',
        'comm_code',
        'comm_desc',
        'kbli2015',
        'desk_kbli20',
        'hs_desc',
    ];

    // Satu kode HS dapat digunakan oleh banyak transaksi.
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'hs_id', 'hs_id');
    }
}
