<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProxyTest extends TestCase
{
    public function test_links_and_assets_stay_https_behind_a_tls_proxy(): void
    {
        $this->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->get('/login')
            ->assertOk()
            ->assertSee('https://localhost/css/app.css', false)
            ->assertSee('action="https://localhost/login"', false);
    }
}
