<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comment extends Model
{
    // Menggunakan tabel comments.
    protected $table = 'comments';

    // Primary key tabel komentar.
    protected $primaryKey = 'comment_id';

    // Kolom yang boleh diisi melalui proses mass assignment.
    protected $fillable = [
        'user_id',
        'batch_id',
        'komentar',
    ];

    /**
     * Setiap komentar dibuat oleh satu pengguna.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Setiap komentar terkait dengan satu batch data.
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(DataBatch::class, 'batch_id', 'batch_id');
    }
}
