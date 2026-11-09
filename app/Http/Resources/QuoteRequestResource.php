<?php

namespace App\Http\Resources;

use App\Models\QuoteRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\QuoteRequest */
class QuoteRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'service' => $this->service,
            'service_label' => $this->serviceLabel(),
            'locale' => $this->locale,
            'deadline' => $this->deadline,
            'deadline_label' => $this->deadlineLabel(),
            'source_language' => $this->source_language,
            'target_language' => $this->target_language,
            'message' => $this->message,
            'admin_note' => $this->admin_note,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'status_changed_at' => $this->status_changed_at?->toIso8601String(),
            'needs_follow_up' => $this->needsFollowUp(),
            'price' => $this->price,
            'notified_at' => $this->notified_at?->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'mail_error' => $this->mail_error,
            'events' => $this->whenLoaded('events', fn () => $this->events->map(fn ($e) => [
                'type' => $e->type,
                'text' => self::describe($e),
                'user' => $e->user?->name ?? $e->user?->email,
                'created_at' => $e->created_at?->toIso8601String(),
            ])),
            'document_name' => $this->document_name,
            'has_document' => (bool) $this->document_path,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /** Olvasható mondat egy előzmény-bejegyzésről. */
    private static function describe(\App\Models\QuoteRequestEvent $e): string
    {
        $ft = fn ($v) => $v === null || $v === '' ? 'nincs' : number_format((int) $v, 0, ',', ' ').' Ft';

        return match ($e->type) {
            'status' => 'Állapot: '.(QuoteRequest::STATUSES[$e->from] ?? $e->from).' → '.(QuoteRequest::STATUSES[$e->to] ?? $e->to),
            'price' => 'Ár: '.$ft($e->from).' → '.$ft($e->to),
            'note' => 'Belső jegyzet módosítva',
            default => $e->type,
        };
    }
}
