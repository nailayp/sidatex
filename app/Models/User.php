<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'role_id',
    'nip',
    'name',
    'nama',
    'email',
    'username',
    'password',
    'is_active',
])]

#[Hidden([
    'password',
    'remember_token',
])]

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Relasi ke role untuk menentukan hak akses pengguna.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id', 'role_id');
    }

    /**
     * Relasi ke log aktivitas yang dibuat pengguna.
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'user_id', 'id');
    }

    /**
     * Relasi ke komentar yang dibuat pengguna.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'user_id', 'id');
    }

    /**
     * Relasi ke batch yang diunggah pengguna.
     */
    public function uploadedBatches(): HasMany
    {
        return $this->hasMany(DataBatch::class, 'uploaded_by', 'id');
    }

    /**
     * Relasi ke batch yang dirilis pengguna.
     */
    public function releasedBatches(): HasMany
    {
        return $this->hasMany(DataBatch::class, 'released_by', 'id');
    }

    /**
     * Mengatur konversi tipe data dan keamanan password.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
