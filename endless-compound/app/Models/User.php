<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    // Tabella Supabase
    protected $table = 'users';

    // Chiave primaria custom
    protected $primaryKey = 'uid';

    // Supabase non usa created_at/updated_at standard
    public $timestamps = false;

    // La tabella Supabase non ha remember_token
    public function getRememberTokenName(): string
    {
        return '';
    }

    protected $fillable = [
        'username',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'createdat' => 'datetime',
            'lastlogin' => 'datetime',
            'password'  => 'hashed',
        ];
    }

    /**
     * Laravel Auth usa 'name' in molti posti (navbar, ecc.)
     * Lo mappiamo su 'username' tramite accessor.
     */
    public function getNameAttribute(): string
    {
        return $this->username ?? '';
    }

    public function setNameAttribute(string $value): void
    {
        $this->username = $value;
    }
}
