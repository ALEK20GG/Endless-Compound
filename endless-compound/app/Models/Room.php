<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    // Nome tabella esplicito (Supabase usa snake_case plurale, già corretto)
    protected $table = 'rooms';

    // Chiave primaria custom
    protected $primaryKey = 'roid';

    // Supabase usa timestamp senza fuso orario con nome custom
    public $timestamps = false;

    protected $fillable = [
        'name',
        'isprivate',
        'maxplayers',
        'owner_uid',
    ];

    protected $casts = [
        'isprivate'  => 'boolean',
        'createdat'  => 'datetime',
        'maxplayers' => 'integer',
    ];

    /**
     * Relazione: ogni room appartiene a un utente (owner).
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_uid', 'uid');
    }
}
