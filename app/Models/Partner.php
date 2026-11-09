<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Partner logóval; csak publikálás után látszik a weboldalon. */
class Partner extends Model
{
    protected $fillable = ['name', 'url', 'is_published', 'sort'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'sort' => 'integer'];
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? url("/api/partners/{$this->id}/logo").'?v='.$this->updated_at?->timestamp : null;
    }
}
