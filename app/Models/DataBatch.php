<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataBatch extends Model
{
    // Menggunakan tabel data_batches.
    protected $table = 'data_batches';

    // Primary key tabel batch.
    protected $primaryKey = 'batch_id';

    // Kolom yang boleh diisi saat menyimpan batch.
    protected $fillable = [
        'nama_file',
        'jenis_data',
        'status',
        'uploaded_by',
        'uploaded_at',
        'released_by',
        'released_at',
    ];

    /**
     * Pengguna yang mengunggah batch.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by', 'id');
    }

    /**
     * Pengguna yang merilis batch.
     */
    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by', 'id');
    }

    /**
     * Satu batch dapat memiliki banyak transaksi.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'batch_id', 'batch_id');
    }

    /**
     * Satu batch dapat memiliki banyak komentar.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'batch_id', 'batch_id');
    }
}
