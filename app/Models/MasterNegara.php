<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MasterNegara extends Model
{
    // Menentukan tabel dan primary key yang digunakan.
    protected $table = 'master_negara';
    protected $primaryKey = 'negara_id';

    // Kolom yang boleh diisi melalui proses input data.
    protected $fillable = [
        'kode_negara',
        'nama_negara',
        'nation',
    ];

    // Satu negara dapat memiliki banyak transaksi ekspor-impor.
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'negara_id', 'negara_id');
    }
}
