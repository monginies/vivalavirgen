<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use App\Models\WebEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatsTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148';

    private function article(): Article
    {
        $category = Category::create(['name' => 'Sillas', 'code' => 'SIL']);

        return Article::create(['category_id' => $category->id, 'reference' => 'SIL-0001', 'name' => 'Silla de enea', 'rental_price' => 5, 'active' => true]);
    }

    public function test_counts_page_views_searches_and_cart_without_ip(): void
    {
        $article = $this->article();

        $this->withHeaders(['User-Agent' => self::BROWSER, 'Referer' => 'https://www.instagram.com/'])
            ->get('/')->assertOk();
        $this->withHeaders(['User-Agent' => self::BROWSER])->get('/articulo/SIL-0001')->assertOk();
        $this->withHeaders(['User-Agent' => self::BROWSER])->get('/?q=Mesa%20Alta')->assertOk();
        $this->withHeaders(['User-Agent' => self::BROWSER])->post('/pedido/articulo/'.$article->id, ['quantity' => 3]);

        $this->assertSame(3, WebEvent::where('type', 'view')->whereIn('path', ['/', '/articulo/SIL-0001'])->count());
        $view = WebEvent::where('article_id', $article->id)->where('type', 'view')->first();
        $this->assertSame('Instagram', $view->source);
        $this->assertSame('movil', $view->device);
        $this->assertSame(1, WebEvent::where('type', 'search')->where('term', 'mesa alta')->where('value', 0)->count());
        $this->assertSame(3, WebEvent::where('type', 'cart_add')->value('value'));
        $this->assertSame(1, WebEvent::distinct()->count('visitor'));
        $this->assertNotContains('ip', array_keys($view->getAttributes()));
    }

    public function test_ignores_bots_and_staff(): void
    {
        $this->article();
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1)'])->get('/')->assertOk();
        $this->actingAs(User::factory()->create())->withHeaders(['User-Agent' => self::BROWSER])->get('/')->assertOk();

        $this->assertSame(0, WebEvent::count());
    }

    public function test_stats_page_for_superadmin(): void
    {
        $article = $this->article();
        $this->withHeaders(['User-Agent' => self::BROWSER])->get('/articulo/SIL-0001');
        $this->withHeaders(['User-Agent' => self::BROWSER])->get('/?q=farol');

        $admin = User::factory()->create(['is_superadmin' => true]);
        foreach ([7, 30, 90, 365] as $days) {
            $this->actingAs($admin)->get('/app/stats?dias='.$days)->assertOk()
                ->assertSee('Estadísticas de la web')->assertSee('Silla de enea')->assertSee('farol');
        }
        $this->actingAs(User::factory()->create())->get('/app/stats')->assertForbidden();
    }

    public function test_sitemap_lists_published_articles(): void
    {
        $this->article();
        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('/articulo/SIL-0001', false)->assertSee('/coleccion/sillas', false);
    }
}
