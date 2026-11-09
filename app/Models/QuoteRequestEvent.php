<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Egy változás az ajánlatkérésen (állapot, ár, jegyzet), az admin felület előzmények listájához. */
class QuoteRequestEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'type', 'from', 'to', 'note'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
