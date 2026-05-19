<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Compound extends Model
{
    protected $table      = 'compounds';
    protected $primaryKey = 'cid';
    public    $timestamps = false;

    protected $fillable = [
        'name',
        'emoji',
        'discoveredat',
        'first_discoverer_uid',
    ];

    protected $casts = [
        'discoveredat' => 'datetime',
    ];

    // ── Relazioni ────────────────────────────────────────────────

    public function firstDiscoverer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'first_discoverer_uid', 'uid');
    }

    /**
     * Ricette in cui questo compound è ingrediente A.
     */
    public function recipesAsA(): HasMany
    {
        return $this->hasMany(Recipe::class, 'cid_a', 'cid');
    }

    /**
     * Ricette in cui questo compound è ingrediente B.
     */
    public function recipesAsB(): HasMany
    {
        return $this->hasMany(Recipe::class, 'cid_b', 'cid');
    }

    /**
     * Ricette in cui questo compound è il risultato.
     */
    public function recipesAsResult(): HasMany
    {
        return $this->hasMany(Recipe::class, 'cid_result', 'cid');
    }

    // ── Helper statici ───────────────────────────────────────────

    /**
     * Cerca un compound per nome (case-insensitive) usando DB::table diretto.
     */
    public static function findByName(string $name): ?self
    {
        $row = \DB::table('compounds')
            ->whereRaw('LOWER(name) = ?', [strtolower(trim($name))])
            ->first();

        if (! $row) return null;

        $instance = new static();
        $instance->setRawAttributes((array) $row, true);
        $instance->exists = true;
        return $instance;
    }
}
