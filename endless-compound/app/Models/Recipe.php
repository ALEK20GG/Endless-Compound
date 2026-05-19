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
     * Usa DB::table diretto per coerenza con il resto delle query.
     */
    public static function findByIngredients(int $cidX, int $cidY): ?self
    {
        [$a, $b] = $cidX <= $cidY ? [$cidX, $cidY] : [$cidY, $cidX];

        $row = \DB::table('recipes')
            ->where('cid_a', $a)
            ->where('cid_b', $b)
            ->first();

        if (! $row) return null;

        $instance = new static();
        $instance->setRawAttributes((array) $row, true);
        $instance->exists = true;

        // Carica il compound risultato
        $resultRow = \DB::table('compounds')->where('cid', $row->cid_result)->first();
        if ($resultRow) {
            $result = new Compound();
            $result->setRawAttributes((array) $resultRow, true);
            $result->exists = true;
            $instance->setRelation('result', $result);
        }

        return $instance;
    }

    /**
     * Crea una ricetta normalizzando l'ordine.
     */
    public static function createNormalized(int $cidX, int $cidY, int $cidResult): self
    {
        [$a, $b] = $cidX <= $cidY ? [$cidX, $cidY] : [$cidY, $cidX];

        \DB::table('recipes')->insert([
            'cid_a'      => $a,
            'cid_b'      => $b,
            'cid_result' => $cidResult,
            'createdat'  => now(),
        ]);

        $instance = new static();
        $instance->setRawAttributes([
            'cid_a'      => $a,
            'cid_b'      => $b,
            'cid_result' => $cidResult,
            'createdat'  => now(),
        ], true);
        $instance->exists = true;
        return $instance;
    }
}
