<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    protected $fillable = ['quote_request_id', 'title', 'due_at', 'done_at'];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'done_at' => 'datetime'];
    }

    public function quoteRequest(): BelongsTo
    {
        return $this->belongsTo(QuoteRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'due_at' => $this->due_at?->toIso8601String(),
            'done_at' => $this->done_at?->toIso8601String(),
            'overdue' => ! $this->done_at && $this->due_at?->isPast(),
            'quote' => $this->quoteRequest ? ['id' => $this->quoteRequest->id, 'name' => $this->quoteRequest->name] : null,
            'user' => $this->user?->name,
        ];
    }
}
