<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    // Tabel yang digunakan oleh model ini.
    protected $table = 'roles';

    // Primary key tabel roles.
    protected $primaryKey = 'role_id';

    // Tabel roles tidak memiliki kolom created_at dan updated_at.
    public $timestamps = false;

    // Kolom yang boleh diisi melalui proses mass assignment.
    protected $fillable = [
        'nama_role',
    ];

    /**
     * Satu role dapat dimiliki oleh banyak pengguna.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role_id', 'role_id');
    }
}
