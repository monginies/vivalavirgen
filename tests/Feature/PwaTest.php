<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifests_service_worker_and_offline_page(): void
    {
        Setting::flushCache();
        $this->get(route('pwa.manifest'))->assertOk()->assertJson(['start_url' => '/?source=app', 'display' => 'standalone']);
        $this->get(route('pwa.app-manifest'))->assertOk()->assertJson(['scope' => '/app/', 'short_name' => 'VLV Gestión']);
        $this->get(route('pwa.sw'))->assertOk()->assertHeader('Content-Type', 'application/javascript; charset=utf-8')->assertSee('showNotification', false);
        $this->get(route('pwa.offline'))->assertOk()->assertSee('Sin conexión');

        $this->get(route('portal.home'))->assertSee('manifest.webmanifest', false);
        $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertSee(route('pwa.app-manifest'), false);
    }
}
