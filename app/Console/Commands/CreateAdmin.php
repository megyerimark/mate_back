<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateAdmin extends Command
{
    protected $signature = 'admin:create
        {email : Az admin e-mail címe}
        {--name=Admin : Megjelenített név}
        {--generate : Erős jelszót generál és kiírja (nem kérdez rá)}';

    protected $description = 'Admin felhasználó létrehozása vagy jelszavának megváltoztatása';

    public function handle(): int
    {
        $email = $this->argument('email');

        if ($this->option('generate')) {
            $password = $confirm = self::randomPassword();
        } else {
            $password = $this->secret('Jelszó (legalább 10 karakter)');
            $confirm = $this->secret('Jelszó újra');
        }

        $validator = Validator::make(
            ['email' => $email, 'password' => $password, 'password_confirmation' => $confirm],
            ['email' => ['required', 'email'], 'password' => ['required', 'string', 'min:10', 'confirmed']],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => $this->option('name'), 'password' => $password],
        );

        // Jelszócsere után a régi bejelentkezések érvénytelenek.
        $user->tokens()->delete();

        $this->info($user->wasRecentlyCreated ? "Admin létrehozva: {$email}" : "Jelszó frissítve: {$email}");
        if ($this->option('generate')) {
            $this->line("Jelszó: <comment>{$password}</comment>");
            $this->line('Írd fel, mert később nem lehet visszanézni. Bejelentkezés után az admin felületen megváltoztatható.');
        }

        return self::SUCCESS;
    }

    /** Erős, 18 karakteres jelszó betűkből és számokból (könnyű begépelni, nincs benne írásjel). */
    public static function randomPassword(): string
    {
        return Str::password(18, symbols: false);
    }
}
