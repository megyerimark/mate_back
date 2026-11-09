<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    private function login(User $user, string $password = 'regi-jelszo-123'): string
    {
        return $this->postJson('/api/admin/login', ['email' => $user->email, 'password' => $password])->json('token');
    }

    public function test_admin_can_add_a_colleague_who_gets_a_generated_password(): void
    {
        $me = User::factory()->create(['password' => 'regi-jelszo-123']);
        $token = $this->login($me);

        $res = $this->withToken($token)->postJson('/api/admin/users', ['name' => 'Kolléga', 'email' => 'kollega@example.com'])->assertCreated();
        $password = $res->json('password');
        $this->assertGreaterThanOrEqual(18, strlen($password));
        $this->assertTrue(Hash::check($password, User::where('email', 'kollega@example.com')->value('password')));

        $this->withToken($token)->postJson('/api/admin/users', ['name' => 'Más', 'email' => 'kollega@example.com'])
            ->assertJsonValidationErrors(['email' => 'már van admin']);

        $this->withToken($token)->getJson('/api/admin/users')->assertJsonCount(2, 'data')->assertJsonFragment(['email' => $me->email, 'is_me' => true]);
    }

    public function test_admin_cannot_delete_themselves_but_can_delete_others(): void
    {
        $me = User::factory()->create(['password' => 'regi-jelszo-123']);
        $other = User::factory()->create();
        $token = $this->login($me);

        $this->withToken($token)->deleteJson("/api/admin/users/{$me->id}")->assertStatus(422);
        $this->withToken($token)->deleteJson("/api/admin/users/{$other->id}")->assertOk();
        $this->assertModelMissing($other);
    }

    public function test_password_change_needs_the_current_password_and_logs_out_other_devices(): void
    {
        $me = User::factory()->create(['password' => 'regi-jelszo-123']);
        $phone = $this->login($me);
        $laptop = $this->login($me);

        $this->withToken($laptop)->putJson('/api/admin/password', [
            'current_password' => 'rossz', 'password' => 'uj-jelszo-12345', 'password_confirmation' => 'uj-jelszo-12345',
        ])->assertJsonValidationErrors(['current_password' => 'nem helyes']);

        $this->withToken($laptop)->putJson('/api/admin/password', [
            'current_password' => 'regi-jelszo-123', 'password' => 'uj-jelszo-12345', 'password_confirmation' => 'uj-jelszo-12345',
        ])->assertOk();

        $this->assertTrue(Hash::check('uj-jelszo-12345', $me->fresh()->password));
        $this->assertSame(1, $me->tokens()->count(), 'csak az aktuális eszköz marad bejelentkezve');
        $this->app['auth']->forgetGuards();
        $this->withToken($phone)->getJson('/api/admin/me')->assertUnauthorized();
    }

    public function test_admin_create_command_can_generate_a_password_and_the_seeder_uses_env(): void
    {
        $this->artisan('admin:create', ['email' => 'info@example.com', '--name' => 'Máté', '--generate' => true])
            ->expectsOutputToContain('Admin létrehozva: info@example.com')
            ->expectsOutputToContain('Jelszó:')
            ->assertSuccessful();

        putenv('ADMIN_EMAIL=elso@example.com');
        putenv('ADMIN_PASSWORD=seed-jelszo-123');
        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();
        putenv('ADMIN_EMAIL');
        putenv('ADMIN_PASSWORD');

        $this->assertTrue(Hash::check('seed-jelszo-123', User::where('email', 'elso@example.com')->value('password')));
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }
}
