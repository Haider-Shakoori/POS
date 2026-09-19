<?php

namespace Tests\Feature;

use Tests\TestCase;

class FoundationTest extends TestCase
{
    public function test_pos_foundation_uses_afghanistan_business_defaults(): void
    {
        $this->assertSame('AFN', config('pos.currency.code'));
        $this->assertFalse(config('pos.tax_enabled'));
        $this->assertSame('Asia/Kabul', config('app.timezone'));
        $this->assertSame(['en', 'fa', 'ps'], array_keys(config('pos.locales')));
    }

    public function test_login_page_is_available(): void
    {
        $this->withoutVite();

        $this->get('/login')->assertOk();
    }
}
