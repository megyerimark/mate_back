<?php

namespace Tests\Feature;

use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use ZipArchive;

class AdminStatsExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-15 12:00:00', 'Europe/Budapest'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function quote(array $attrs = [], array $forced = []): QuoteRequest
    {
        $q = QuoteRequest::create(array_merge([
            'name' => 'Teszt Elek', 'email' => 'elek@example.com', 'deadline' => 'normal',
            'service' => 'translation', 'locale' => 'hu', 'target_language' => 'angol',
        ], $attrs));
        if ($forced) {
            $q->forceFill($forced)->save();
        }

        return $q->refresh();
    }

    public function test_stats_and_export_require_login(): void
    {
        $this->getJson('/api/admin/stats/overview')->assertUnauthorized();
        $this->getJson('/api/admin/quote-requests/export')->assertUnauthorized();
    }

    public function test_overview_counts_months_breakdowns_and_conversion(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->quote(['target_language' => 'Német'], ['status' => 'won']);
        $this->quote(['target_language' => 'német ', 'locale' => 'en', 'service' => 'interpreting'], ['status' => 'lost']);
        $this->quote([], ['status' => 'won', 'created_at' => Carbon::parse('2026-09-10 10:00')]);
        $this->quote([], ['created_at' => Carbon::parse('2025-01-10 10:00')]); // 12 hónapon kívül

        $res = $this->getJson('/api/admin/stats/overview')->assertOk();

        $res->assertJsonPath('totals.total', 3)
            ->assertJsonPath('totals.won', 2)
            ->assertJsonPath('totals.lost', 1)
            ->assertJsonPath('totals.conversion', 67)
            ->assertJsonPath('totals.english', 1)
            ->assertJsonPath('this_month', 2)
            ->assertJsonPath('last_month', 0)
            ->assertJsonCount(12, 'monthly')
            ->assertJsonPath('monthly.11', ['month' => '2026-11', 'label' => '2026. nov.', 'total' => 2, 'won' => 1, 'revenue' => 0])
            ->assertJsonPath('monthly.9.total', 1)
            ->assertJsonPath('top_languages.0', ['label' => 'német', 'total' => 2])
            ->assertJsonFragment(['key' => 'interpreting', 'label' => 'Tolmácsolás', 'total' => 1])
            ->assertJsonFragment(['key' => 'en', 'label' => 'Angol oldal', 'total' => 1]);

        $this->getJson('/api/admin/stats/overview?period=all')->assertOk()->assertJsonPath('totals.total', 4)->assertJsonPath('from', '2025-01-01');
        $this->getJson('/api/admin/stats/overview?period=year')->assertOk()->assertJsonCount(11, 'monthly')->assertJsonPath('from', '2026-01-01');
        $this->getJson('/api/admin/stats/overview?period=valami')->assertUnprocessable();
    }

    public function test_export_is_a_real_xlsx_with_filtered_rows(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->quote(['name' => 'Kovács Anna', 'message' => "Két sor\nüzenet <&>"], ['status' => 'won', 'admin_note' => '12 000 Ft']);
        $this->quote(['name' => 'Nagy Péter'], ['status' => 'lost']);
        $this->quote(['name' => 'Régi Ügyfél'], ['status' => 'won', 'created_at' => Carbon::parse('2026-01-05 09:30')]);

        $res = $this->get('/api/admin/quote-requests/export?status=won&from=2026-11-01');
        $res->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('Content-Disposition', 'attachment; filename="ajanlatkeresek-2026-11-15.xlsx"');

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $res->getContent());
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true, 'Az export nem érvényes zip/xlsx fájl.');
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertNotFalse($zip->getFromName('xl/workbook.xml'));
        $zip->close();
        unlink($path);

        $this->assertNotFalse(simplexml_load_string($sheet), 'A munkalap nem érvényes XML.');
        $this->assertStringContainsString('Kovács Anna', $sheet);
        $this->assertStringContainsString('Két sor'."\n".'üzenet &lt;&amp;&gt;', $sheet);
        $this->assertStringContainsString('12 000 Ft', $sheet);
        $this->assertStringContainsString('Megrendelve', $sheet);
        $this->assertStringNotContainsString('Nagy Péter', $sheet);   // állapot szűrő
        $this->assertStringNotContainsString('Régi Ügyfél', $sheet);  // dátum szűrő
        $this->assertStringContainsString('<autoFilter ref="A1:O2"/>', $sheet);
    }
}
