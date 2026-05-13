<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recipe extends Model
{
    protected $table      = 'recipes';
    public    $timestamps = false;

    // PK composta — Eloquent non la gestisce nativamente,
    // usiamo incrementing = false e gestiamo manualmente gli insert.
    public    $incrementing = false;

    protected $fillable = [
        'cid_a',
        'cid_b',
        'cid_result',
        'createdat',
    ];

    protected $casts = [
        'createdat' => 'datetime',
    ];

    // ── Relazioni ────────────────────────────────────────────────

    public function ingredientA(): BelongsTo
    {
        return $this->belongsTo(Compound::class, 'cid_a', 'cid');
    }

    public function ingredientB(): BelongsTo
    {
        return $this->belongsTo(Compound::class, 'cid_b', 'cid');
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(Compound::class, 'cid_result', 'cid');
    }

    // ── Helper statici ───────────────────────────────────────────

    /**
     * Cerca una ricetta normalizzando l'ordine (cid_a <= cid_b).
     */
    public static function findByIngredients(int $cidX, int $cidY): ?self
    {
        [$a, $b] = $cidX <= $cidY ? [$cidX, $cidY] : [$cidY, $cidX];

        return static::with('result')
            ->where('cid_a', $a)
            ->where('cid_b', $b)
            ->first();
    }

    /**
     * Crea una ricetta normalizzando l'ordine.
     */
    public static function createNormalized(int $cidX, int $cidY, int $cidResult): self
    {
        [$a, $b] = $cidX <= $cidY ? [$cidX, $cidY] : [$cidY, $cidX];

        return static::create([
            'cid_a'      => $a,
            'cid_b'      => $b,
            'cid_result' => $cidResult,
            'createdat'  => now(),
        ]);
    }
}
