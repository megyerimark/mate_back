<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuoteRequest extends Model
{
    /** Az ajánlatkérés útja az admin felületen, a beérkezéstől a megrendelésig. */
    public const STATUSES = [
        'new' => 'Új',
        'in_progress' => 'Folyamatban',
        'quoted' => 'Ajánlat elküldve',
        'won' => 'Megrendelve',
        'lost' => 'Elmaradt',
    ];

    /** Melyik szolgáltatásra kértek ajánlatot (a weboldal űrlapján választható). */
    public const SERVICES = [
        'translation' => 'Fordítás',
        'proofreading' => 'Lektorálás',
        'post_editing' => 'Utószerkesztés (PEMT)',
        'interpreting' => 'Tolmácsolás',
        'spelling' => 'Helyesírási tanácsadás',
    ];

    public const INTERPRETING_TYPES = [
        'consecutive' => 'Követő tolmácsolás',
        'simultaneous' => 'Szinkrontolmácsolás',
        'remote' => 'Távtolmácsolás',
        'chuchotage' => 'Susotázs',
        'other' => 'Egyéb',
    ];

    public const LOCALES = ['hu', 'en'];

    public const DEADLINES = [
        'normal' => 'Normál (2–5 munkanap)',
        'urgent' => 'Sürgős (1–2 munkanap)',
        'express' => 'Expressz (akár aznapra)',
    ];

    // A status és az admin_note szándékosan nincs itt: csak az admin felület állítja őket.
    protected $fillable = [
        'name',
        'email',
        'phone',
        'service',
        'interpreting_type',
        'locale',
        'deadline',
        'source_language',
        'target_language',
        'message',
        'document_path',
        'document_name',
        'ip_address',
    ];

    protected $hidden = ['ip_address'];

    /** Ennyi nap után jelezzük, ha egy elküldött ajánlatra nem jött válasz. */
    public const FOLLOW_UP_DAYS = 3;

    protected function casts(): array
    {
        return ['status_changed_at' => 'datetime', 'price' => 'integer', 'notified_at' => 'datetime', 'confirmed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $q) => $q->status_changed_at ??= now());
    }

    public function documents(): HasMany
    {
        return $this->hasMany(QuoteRequestDocument::class)->orderBy('id');
    }

    /**
     * Az ajánlatkérés összes fájlja egységesen: a régi, egyfájlos oszlop (korábbi kéréseknél) és az új tábla.
     *
     * @return list<array{path: string, name: string, size: ?int, mime: ?string}>
     */
    public function files(): array
    {
        $files = [];
        if ($this->document_path) {
            $files[] = ['path' => $this->document_path, 'name' => $this->document_name ?? 'dokumentum', 'size' => null, 'mime' => null];
        }
        foreach ($this->documents as $d) {
            $files[] = ['path' => $d->path, 'name' => $d->name, 'size' => $d->size, 'mime' => $d->mime];
        }

        return $files;
    }

    public function events(): HasMany
    {
        return $this->hasMany(QuoteRequestEvent::class)->latest('id');
    }

    /** Elküldött ajánlat, amire FOLLOW_UP_DAYS napja nem változott semmi: érdemes rákérdezni. */
    public function needsFollowUp(): bool
    {
        return $this->status === 'quoted'
            && $this->status_changed_at !== null
            && $this->status_changed_at->lte(now()->subDays(self::FOLLOW_UP_DAYS));
    }

    public function scopeAwaitingFollowUp(Builder $query): Builder
    {
        return $query->where('status', 'quoted')->where('status_changed_at', '<=', now()->subDays(self::FOLLOW_UP_DAYS));
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function serviceLabel(): string
    {
        $label = self::SERVICES[$this->service] ?? $this->service;

        return $this->interpreting_type
            ? $label.' – '.(self::INTERPRETING_TYPES[$this->interpreting_type] ?? $this->interpreting_type)
            : $label;
    }

    public function deadlineLabel(): string
    {
        return self::DEADLINES[$this->deadline] ?? $this->deadline;
    }
}
