<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Élesítéskor: kimegy-e a levél a beállított SMTP-vel. */
class MailTest extends Command
{
    protected $signature = 'mail:test {to? : Címzett (alapértelmezés: QUOTE_RECIPIENT_EMAIL)}';

    protected $description = 'Próba e-mail küldése a .env levelezési beállításaival';

    public function handle(): int
    {
        $to = $this->argument('to') ?: config('quotes.recipient');
        $mailer = config('mail.default');
        $this->line("Küldő: {$mailer}".($mailer === 'smtp' ? ' ('.config('mail.mailers.smtp.host').':'.config('mail.mailers.smtp.port').')' : ''));
        $this->line('Feladó: '.config('mail.from.address')."  →  Címzett: {$to}");

        if (in_array($mailer, ['log', 'array'], true)) {
            $this->warn("A MAIL_MAILER most „{$mailer}”, így a levél nem megy ki valóban (csak a naplóba kerül). Élesben: MAIL_MAILER=smtp.");
        }

        try {
            Mail::raw(
                "Ez egy próbalevél a ".config('app.name')." weboldaláról.\n\nHa megkaptad, a levélküldés be van állítva: az ajánlatkérések értesítői és az ügyfeleknek szóló visszaigazolások ki fognak menni.",
                fn ($m) => $m->to($to)->subject('Próbalevél – '.config('app.name')),
            );
        } catch (Throwable $e) {
            $this->error('Nem sikerült: '.$e->getMessage());
            $this->line('Ellenőrizd a MAIL_HOST, MAIL_PORT, MAIL_SCHEME, MAIL_USERNAME és MAIL_PASSWORD sorokat a .env-ben.');

            return self::FAILURE;
        }

        $this->info('Elküldve. Nézd meg a postafiókot (a levélszemét mappát is).');

        return self::SUCCESS;
    }
}
