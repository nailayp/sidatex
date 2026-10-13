<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    // Menggunakan tabel activity_logs.
    protected $table = 'activity_logs';

    // Primary key tabel.
    protected $primaryKey = 'log_id';

    // Tabel ini hanya memiliki created_at, tanpa updated_at.
    public const UPDATED_AT = null;

    // Kolom yang boleh diisi saat mencatat aktivitas.
    protected $fillable = [
        'user_id',
        'aktivitas',
        'modul',
        'deskripsi',
        'ip_address',
    ];

    /**
     * Setiap catatan aktivitas dimiliki oleh satu pengguna.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
