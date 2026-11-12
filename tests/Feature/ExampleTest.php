<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_catalog_and_backoffice_login(): void
    {
        $this->get('/')->assertOk()->assertSee('Viva la Virgen');
        $this->get('/app')->assertRedirect(route('login'));
        $this->get('/app/orders')->assertRedirect('/app/login');
        $this->get('/mi-cuenta')->assertRedirect(route('portal.login'));
    }
}
