<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Valódi ügyfélvélemény; csak publikálás után látszik a weboldalon. */
class Testimonial extends Model
{
    protected $fillable = ['name', 'company', 'role', 'text', 'locale', 'rating', 'is_published', 'sort'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'rating' => 'integer', 'sort' => 'integer'];
    }

    public function scopePublishedFor(Builder $q, string $lang): Builder
    {
        return $q->where('is_published', true)->whereIn('locale', [$lang, 'all'])->orderBy('sort')->orderByDesc('id');
    }
}
