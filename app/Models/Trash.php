<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Trash extends Model
{
    // Menyimpan snapshot data batch yang dihapus agar bisa dipulihkan.
    protected $table = 'trash';
    protected $primaryKey = 'trash_id';

    // Tabel trash tidak memiliki updated_at.
    public const UPDATED_AT = null;

    protected $fillable = [
        'batch_id',
        'data_snapshot',
        'deleted_by',
        'deleted_at',
        'restored_by',
        'restored_at',
    ];

    // Snapshot mengubah JSON database menjadi array PHP.
    protected function casts(): array
    {
        return [
            'data_snapshot' => 'array',
            'deleted_at' => 'datetime',
            'restored_at' => 'datetime',
        ];
    }

    // Batch yang menjadi sumber data snapshot.
    public function batch(): BelongsTo
    {
        return $this->belongsTo(DataBatch::class, 'batch_id', 'batch_id');
    }

    // Pegawai yang menghapus data.
    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by', 'id');
    }

    // Pegawai yang memulihkan data, jika sudah dipulihkan.
    public function restorer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'restored_by', 'id');
    }
}
