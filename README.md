# Done Translations – backend

**Laravel 13** API a Done Translations weboldalhoz: árajánlatkérések fogadása, e-mail értesítés, admin felület API.
A hozzá tartozó frontend (Angular): [mate_front](https://github.com/megyerimark/mate_front).

## Fejlesztés

Kell hozzá: PHP 8.3+ és Composer.

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan admin:create admin@donetranslations.hu   # bekéri a jelszót
php artisan serve                                    # http://localhost:8000
```

Helyben a kimenő e-mailek a `storage/logs/laravel.log` fájlba kerülnek (`MAIL_MAILER=log`).

Tesztek: `php artisan test`

## Hogyan működik az ajánlatkérés?

1. Az ügyfél kitölti az űrlapot a weboldalon, és opcionálisan feltölti a dokumentumot.
2. `POST /api/quote-requests`: a Laravel ellenőrzi az adatokat, elmenti az adatbázisba, a fájlt privát tárhelyre (`storage/app/private/quote-requests`).
3. A fordító e-mailt kap az összes adattal **és a csatolt dokumentummal**. A „Válasz” gomb közvetlenül az ügyfélnek ír.
4. Az ügyfél automatikus visszaigazoló e-mailt kap (ajánlatkérés, tolmácsfoglalás, dokumentumküldés és helyesírási kérdés esetén is),
   benne a logóval és annak összefoglalójával, amit beküldött (szolgáltatás, nyelvek, határidő, üzenet; a dokumentumot külön visszaigazolja).
5. Védelem: rejtett mezős spamszűrő (honeypot), IP-alapú korlát (5 kérés/perc, 30/nap), fájltípus- és méretellenőrzés (max. 8 MB).

Az űrlap a szolgáltatást is elküldi (`service`: translation, proofreading, post_editing, interpreting, spelling),
tolmácsolásnál a típusát (`interpreting_type`), és a weboldal nyelvét (`locale`: hu/en). Az angol oldalról érkező
ügyfél angol hibaüzeneteket és angol visszaigazoló e-mailt kap.

## A weboldal kiszolgálása (régi WordPress címek)

A frontend előrenderelt oldalait (`resources/frontend`, a frontend `npm run build:backend` parancsa másolja ide)
a `FrontendController` szolgálja ki, úgy, hogy a Google-helyezés megmaradjon:

- a címek ugyanazok, mint a régi WordPress oldalon, perjellel a végén (`/forditas/`);
- perjel nélküli cím (`/forditas`) → 301-es átirányítás a perjelesre;
- a régi Yoast sitemap (`/sitemap_index.xml`, `/page-sitemap.xml`) → 301 a `/sitemap.xml`-re;
- ismeretlen cím → valódi **404**; az `/admin` a böngészőben fut.

Ezért a `public/.htaccess`-ből kivettük a Laravel „Redirect Trailing Slashes” szabályát (az levágná a perjelet).
**Nginx** esetén a szokásos Laravel beállítás kell, `$uri/` nélkül: `try_files $uri /index.php?$query_string;`

Az e-mailek a weboldal színeit használják: `resources/views/vendor/mail/html/themes/donetranslations.css`.
A fejlécben lévő logó: `public/email/done-translations-logo.png` (400 px széles PNG; ha a logó változik, ezt a fájlt kell lecserélni).
A kép teljes címmel kerül a levélbe, ezért élesben az `APP_URL` legyen `https://donetranslations.hu`.

## Admin

Admin felhasználó létrehozása, vagy jelszavának megváltoztatása (ilyenkor a régi bejelentkezések megszűnnek):

```bash
php artisan admin:create baratom@example.com --name="Barátom"
```

Nyilvános regisztráció nincs. A bejelentkezés tokennel működik (Laravel Sanctum), 12 óráig érvényes
(`SANCTUM_EXPIRATION`, percben). Hibás jelszóval percenként legfeljebb 5 próba lehet.

| Végpont | Mire |
|---|---|
| `POST /api/admin/login` | bejelentkezés, tokent ad |
| `POST /api/admin/logout` | kijelentkezés |
| `GET /api/admin/stats` | összesítő számok állapot szerint |
| `GET /api/admin/stats/overview` | statisztika (`period`: `12m`, `year`, `all`): havi bontás, megrendelési arány, szolgáltatás, nyelv, határidő stb. |
| `GET /api/admin/quote-requests` | lista (`status`, `follow_up`, `search`, `from`, `to`, `page`) |
| `GET /api/admin/quote-requests/export` | Excel (.xlsx) a lista szűrőivel, lapozás nélkül |
| `GET /api/admin/quote-requests/{id}` | egy ajánlatkérés |
| `PATCH /api/admin/quote-requests/{id}` | állapot (`status`), ár forintban (`price`) és belső jegyzet (`admin_note`); minden változás bekerül az előzményekbe |
| `DELETE /api/admin/quote-requests/{id}` | törlés a feltöltött fájllal együtt |
| `GET /api/admin/quote-requests/{id}/document` | a feltöltött dokumentum letöltése |

Utánkövetés: ha egy ajánlat 3 napja „Ajánlat elküldve” állapotban van (`QuoteRequest::FOLLOW_UP_DAYS`), az admin jelzi.

Állapotok: `new` (Új), `in_progress` (Folyamatban), `quoted` (Ajánlat elküldve), `won` (Megrendelve), `lost` (Elmaradt).

## reCAPTCHA

Az űrlapokat Google reCAPTCHA v3 védi (láthatatlan, a látogatónak nem kell kattintgatnia), ugyanazzal a kulccsal, mint a régi WordPress oldalt.
A nyilvános kulcs a frontendben van (`environment.ts`), a **titkos kulcsot** a `.env`-be kell írni:

```
RECAPTCHA_SECRET_KEY=...   # a régi WordPressben: Elementor → Beállítások → Integrációk, vagy https://www.google.com/recaptcha/admin
RECAPTCHA_MIN_SCORE=0.5    # 0 = biztosan bot, 1 = biztosan ember
```

Üres titkos kulccsal az ellenőrzés kikapcsol. Ha a Google épp nem érhető el, az ajánlatkérés átmegy (a honeypot és a percenkénti korlát továbbra is véd).

## Élesítés

1. A frontendben: `npm run build:backend` – bemásolja a lebuildelt oldalt ennek a projektnek a `public` mappájába.
   Így a Laravel szolgálja ki a weboldalt, az admin felületet és az API-t is, **egy domainen** (a frontend és a backend
   így össze van kötve: az űrlap a `/api/quote-requests` címre küld, nincs külön beállítanivaló).
2. Ezt a projektet töltsd fel a tárhelyre. A domain gyökere (document root) a `public` mappára mutasson.
3. A tárhelyen: `composer install --no-dev --optimize-autoloader`, `.env` kitöltése (lásd lent), `php artisan key:generate`,
   `php artisan migrate --force`.
4. **Admin létrehozása** (egyszer):
   ```
   php artisan admin:create info@donetranslations.hu --name="Szatmáry Máté" --generate
   ```
   Kiírja a generált jelszót. (Vagy: `ADMIN_EMAIL` / `ADMIN_NAME` a `.env`-ben, majd `php artisan db:seed --force`.)
   Belépés: `https://donetranslations.hu/admin` → a **Fiók** oldalon a jelszó megváltoztatható, és további adminok adhatók hozzá.
5. **Levelezés** – a donetranslations.hu levelezőszervere (RackForest cPanel, `mail.donetranslations.hu`).
   Az SPF és a DKIM már be van állítva a domainen, így az innen küldött levelek nem mennek levélszemétbe.
   ```
   MAIL_MAILER=smtp
   MAIL_SCHEME=smtps
   MAIL_HOST=mail.donetranslations.hu
   MAIL_PORT=465
   MAIL_USERNAME=info@donetranslations.hu
   MAIL_PASSWORD=...              # az info@ postafiók jelszava (cPanel → E-mail fiókok)
   MAIL_FROM_ADDRESS=info@donetranslations.hu
   QUOTE_RECIPIENT_EMAIL=info@donetranslations.hu   # ide jönnek az ajánlatkérések
   ```
   Próba: `php artisan mail:test` – küld egy levelet a `QUOTE_RECIPIENT_EMAIL` címre.
   Ha egy levél nem megy ki, az ajánlatkérés akkor is mentődik; az admin felületen „E-mail hiba” jelzés látszik, és onnan újraküldhető.
6. Többi fontos `.env` sor:
   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://donetranslations.hu
   DB_CONNECTION=mysql            # vagy maradhat sqlite
   RECAPTCHA_SECRET_KEY=...       # lásd a reCAPTCHA részt
   # Csak ha a frontend külön domainen fut:
   CORS_ALLOWED_ORIGINS=https://donetranslations.hu
   ```
7. A domain DNS-ét a tárhelyre kell állítani (ezt a domain-regisztrátornál lehet).
