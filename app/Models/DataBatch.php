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
        'ukuran_file',
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
     * Relasi ke transaksi Distribusi fisik (distribusi_transactions).
     */
    public function distribusiTransactions(): HasMany
    {
        return $this->hasMany(DistribusiTransaction::class, 'batch_id', 'batch_id');
    }

    /**
     * Relasi ke transaksi Neraca fisik (neraca_transactions).
     */
    public function neracaTransactions(): HasMany
    {
        return $this->hasMany(NeracaTransaction::class, 'batch_id', 'batch_id');
    }

    /**
     * Satu batch dapat memiliki banyak transaksi (mengarah ke DistribusiTransaction via Transaction model).
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
