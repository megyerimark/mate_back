<?php

namespace Database\Seeders;

use App\Console\Commands\CreateAdmin;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Az első admin felhasználó a .env alapján (ADMIN_EMAIL, ADMIN_NAME, ADMIN_PASSWORD).
     * Ha nincs jelszó megadva, generál egyet és kiírja. Meglévő admint nem ír felül.
     * Futtatás: php artisan db:seed --force
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        if (! $email) {
            $this->command?->warn('Nincs ADMIN_EMAIL a .env-ben, nem jött létre admin. (Vagy: php artisan admin:create e-mail --generate)');

            return;
        }

        if (User::where('email', $email)->exists()) {
            $this->command?->info("Az admin már létezik: {$email}");

            return;
        }

        $password = env('ADMIN_PASSWORD') ?: CreateAdmin::randomPassword();
        User::create(['name' => env('ADMIN_NAME', 'Admin'), 'email' => $email, 'password' => $password]);

        $this->command?->info("Admin létrehozva: {$email}");
        if (! env('ADMIN_PASSWORD')) {
            $this->command?->line("Jelszó: {$password}  (írd fel; az admin felületen megváltoztatható)");
        }
    }
}
