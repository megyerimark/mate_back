<?php

namespace Tests\Feature;

use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminQuoteWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function quote(array $attrs = []): QuoteRequest
    {
        $q = QuoteRequest::create(['name' => 'Kovács Anna', 'email' => 'anna@example.com', 'deadline' => 'normal', 'target_language' => 'angol']);
        $q->forceFill($attrs)->save();

        return $q;
    }

    public function test_status_price_and_note_changes_are_logged_in_the_history(): void
    {
        Sanctum::actingAs(User::factory()->create(['name' => 'Máté']));
        $q = $this->quote();

        $this->patchJson("/api/admin/quote-requests/{$q->id}", ['status' => 'quoted', 'price' => 12000])
            ->assertOk()
            ->assertJsonPath('data.price', 12000)
            ->assertJsonPath('data.events.0.text', 'Ár: nincs → 12 000 Ft')
            ->assertJsonPath('data.events.1.text', 'Állapot: Új → Ajánlat elküldve')
            ->assertJsonPath('data.events.0.user', 'Máté');

        // Ugyanaz az érték újra: nem kerül be új bejegyzés.
        $this->patchJson("/api/admin/quote-requests/{$q->id}", ['status' => 'quoted', 'price' => 12000])->assertOk();
        $this->patchJson("/api/admin/quote-requests/{$q->id}", ['admin_note' => 'kedd délig'])->assertOk();

        $this->getJson("/api/admin/quote-requests/{$q->id}")
            ->assertJsonCount(3, 'data.events')
            ->assertJsonPath('data.events.0.text', 'Belső jegyzet módosítva');

        $this->patchJson("/api/admin/quote-requests/{$q->id}", ['price' => -5])->assertJsonValidationErrors('price');
    }

    public function test_sent_quotes_without_answer_for_three_days_need_follow_up(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Carbon::setTestNow('2026-11-10 10:00');
        $old = $this->quote(['status' => 'quoted', 'status_changed_at' => now()->subDays(4)]);
        $this->quote(['status' => 'quoted', 'status_changed_at' => now()->subDay()]);
        $this->quote(['status' => 'new', 'status_changed_at' => now()->subDays(10)]);

        $this->getJson('/api/admin/quote-requests?follow_up=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $old->id)
            ->assertJsonPath('data.0.needs_follow_up', true);
        $this->getJson('/api/admin/stats')->assertJsonPath('follow_up', 1);

        // Ha válaszolt (megrendelte), már nem kell utánamenni.
        $this->patchJson("/api/admin/quote-requests/{$old->id}", ['status' => 'won'])->assertJsonPath('data.needs_follow_up', false);
        $this->getJson('/api/admin/stats')->assertJsonPath('follow_up', 0);
    }

    public function test_overview_reports_the_value_of_won_orders(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->quote(['status' => 'won', 'price' => 10000]);
        $this->quote(['status' => 'won', 'price' => 30000]);
        $this->quote(['status' => 'won']);
        $this->quote(['status' => 'quoted', 'price' => 99000]);

        $this->getJson('/api/admin/stats/overview')
            ->assertOk()
            ->assertJsonPath('totals.revenue', 40000)
            ->assertJsonPath('totals.average_order', 20000)
            ->assertJsonPath('totals.priced_orders', 2);
    }
}
