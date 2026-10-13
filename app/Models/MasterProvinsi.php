<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MasterProvinsi extends Model
{
    protected $table = 'master_provinsi';
    protected $primaryKey = 'provinsi_id';

    protected $fillable = [
        'kode_provinsi',
        'nama_provinsi',
    ];

    // Satu provinsi memiliki banyak kabupaten/kota dan transaksi.
    public function kabkota(): HasMany
    {
        return $this->hasMany(MasterKabkota::class, 'provinsi_id', 'provinsi_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'provinsi_id', 'provinsi_id');
    }
}
