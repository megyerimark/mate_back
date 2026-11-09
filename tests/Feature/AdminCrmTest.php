<?php

namespace Tests\Feature;

use App\Models\QuoteRequest;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCrmTest extends TestCase
{
    use RefreshDatabase;

    private function quote(array $attrs = []): QuoteRequest
    {
        $q = QuoteRequest::create(['name' => 'Kovács Anna', 'email' => 'anna@example.com', 'deadline' => 'normal']);
        $q->forceFill($attrs)->save();

        return $q;
    }

    public function test_tasks_can_be_created_completed_listed_and_deleted(): void
    {
        $this->getJson('/api/admin/tasks')->assertUnauthorized();
        Carbon::setTestNow('2026-11-10 10:00');
        Sanctum::actingAs(User::factory()->create(['name' => 'Máté']));
        $q = $this->quote();

        $this->postJson('/api/admin/tasks', ['title' => ''])->assertJsonValidationErrors(['title' => 'Írd be']);
        $late = $this->postJson('/api/admin/tasks', ['title' => 'Visszahívni', 'due_at' => '2026-11-09 15:00', 'quote_request_id' => $q->id])
            ->assertCreated()->assertJsonPath('data.overdue', true)->assertJsonPath('data.quote.name', 'Kovács Anna')->json('data.id');
        $this->postJson('/api/admin/tasks', ['title' => 'Számla', 'due_at' => '2026-11-20 12:00'])->assertCreated();

        $this->getJson('/api/admin/tasks')->assertJsonCount(2, 'data')->assertJsonPath('data.0.title', 'Visszahívni');
        $this->getJson("/api/admin/tasks?quote_request_id={$q->id}")->assertJsonCount(1, 'data');
        $this->getJson('/api/admin/stats')->assertJsonPath('tasks_due', 1);

        $this->patchJson("/api/admin/tasks/{$late}", ['done' => true])->assertOk()->assertJsonPath('data.overdue', false);
        $this->getJson('/api/admin/tasks')->assertJsonCount(1, 'data');
        $this->getJson('/api/admin/tasks?status=done')->assertJsonCount(1, 'data');
        $this->getJson('/api/admin/stats')->assertJsonPath('tasks_due', 0);

        $this->deleteJson("/api/admin/tasks/{$late}")->assertOk();
        $this->assertSame(1, Task::count());
    }

    public function test_contact_log_client_history_and_latest_for_realtime(): void
    {
        Sanctum::actingAs(User::factory()->create(['name' => 'Máté']));
        $first = $this->quote(['status' => 'won', 'price' => 20000]);
        $second = $this->quote();
        $this->quote()->forceFill(['email' => 'mas@example.com', 'name' => 'Más Ügyfél'])->save();

        $this->postJson("/api/admin/quote-requests/{$second->id}/contact", ['channel' => 'phone', 'note' => 'Holnapra kéri'])
            ->assertOk()->assertJsonPath('data.events.0.text', 'Telefonhívás: Holnapra kéri')->assertJsonPath('data.events.0.channel', 'phone');
        $this->postJson("/api/admin/quote-requests/{$second->id}/contact", ['channel' => 'fax'])->assertJsonValidationErrors('channel');

        // Ügyféladatlap: ugyanattól az e-mail-címtől érkezett kérések.
        $this->getJson('/api/admin/quote-requests?email=anna@example.com')->assertJsonCount(2, 'data');
        $this->getJson('/api/admin/stats')->assertJsonPath('latest.name', 'Más Ügyfél');
    }

    public function test_overview_weekly_grouping_conversion_by_service_and_response_time(): void
    {
        Carbon::setTestNow('2026-11-10 10:00');
        Sanctum::actingAs(User::factory()->create());
        $a = $this->quote(['status' => 'won', 'service' => 'translation', 'created_at' => now()->subHours(5)]);
        $this->quote(['status' => 'lost', 'service' => 'translation']);
        $this->quote(['status' => 'won', 'service' => 'interpreting']);
        // Állapotváltás 2 órával a beérkezés után.
        $a->events()->create(['type' => 'status', 'from' => 'new', 'to' => 'quoted'])->forceFill(['created_at' => now()->subHours(3)])->save();

        $res = $this->getJson('/api/admin/stats/overview?group=week')->assertOk()->assertJsonPath('group', 'week');
        $this->assertGreaterThanOrEqual(52, count($res->json('monthly')));
        $res->assertJsonFragment(['key' => 'translation', 'label' => 'Fordítás', 'total' => 2, 'won' => 1, 'rate' => 50])
            ->assertJsonFragment(['key' => 'interpreting', 'label' => 'Tolmácsolás', 'total' => 1, 'won' => 1, 'rate' => 100])
            ->assertJsonPath('totals.response_hours', 2)
            ->assertJsonPath('totals.responded', 1);
        $this->getJson('/api/admin/stats/overview?group=year')->assertUnprocessable();
    }

    public function test_a_request_just_after_midnight_on_the_first_counts_in_the_new_month(): void
    {
        // Hiba volt: a határokat UTC-re váltotta, pedig az időbélyegek budapesti idő szerint vannak tárolva.
        Carbon::setTestNow('2026-11-10 10:00');
        Sanctum::actingAs(User::factory()->create());
        $this->quote(['created_at' => '2026-11-01 00:30:00']);

        $this->getJson('/api/admin/stats/overview')->assertJsonPath('monthly.11.month', '2026-11')->assertJsonPath('monthly.11.total', 1)
            ->assertJsonPath('monthly.10.total', 0)->assertJsonPath('this_month', 1)->assertJsonPath('last_month', 0);
    }
}
