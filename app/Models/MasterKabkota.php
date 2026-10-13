<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MasterKabkota extends Model
{
    protected $table = 'master_kabkota';
    protected $primaryKey = 'kabkota_id';

    protected $fillable = [
        'provinsi_id',
        'kode_kabkota',
        'nama_kabkota',
    ];

    // Kabupaten/kota berada di bawah satu provinsi.
    public function provinsi(): BelongsTo
    {
        return $this->belongsTo(MasterProvinsi::class, 'provinsi_id', 'provinsi_id');
    }

    // Satu kabupaten/kota dapat memiliki banyak pelabuhan dan transaksi.
    public function pelabuhan(): HasMany
    {
        return $this->hasMany(MasterPelabuhan::class, 'kabkota_id', 'kabkota_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'kabkota_id', 'kabkota_id');
    }
}
