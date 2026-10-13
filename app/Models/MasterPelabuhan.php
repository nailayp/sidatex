<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MasterPelabuhan extends Model
{
    protected $table = 'master_pelabuhan';
    protected $primaryKey = 'pelabuhan_id';

    protected $fillable = [
        'kabkota_id',
        'kode_pelabuhan',
        'nama_pelabuhan',
    ];

    // Pelabuhan terhubung dengan satu kabupaten/kota.
    public function kabkota(): BelongsTo
    {
        return $this->belongsTo(MasterKabkota::class, 'kabkota_id', 'kabkota_id');
    }

    // Satu pelabuhan dapat muncul di banyak transaksi.
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'pelabuhan_id', 'pelabuhan_id');
    }
}
