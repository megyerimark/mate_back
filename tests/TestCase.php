<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;

abstract class TestCase extends BaseTestCase
{
    private static bool $viewsCleared = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Futásonként egyszer: a lefordított Blade-sablonok törlése. Egy git pull után a régi, lefordított
        // e-mail-sablon különben bent ragadhat, és a levelek tartalmát ellenőrző tesztek tévesen buknak el.
        if (! self::$viewsCleared) {
            Artisan::call('view:clear');
            self::$viewsCleared = true;
        }
    }
}
