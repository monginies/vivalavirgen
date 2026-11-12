<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotosTest extends TestCase
{
    use RefreshDatabase;

    private function article(): Article
    {
        $cat = Category::create(['name' => 'Sillas', 'code' => 'SIL']);

        return Article::create(['category_id' => $cat->id, 'reference' => 'SIL-0001', 'name' => 'Silla', 'rental_price' => 1, 'replacement_price' => 1, 'active' => true]);
    }

    public function test_upload_several_photos_at_once_resizes_and_orders_them(): void
    {
        Storage::fake('public');
        $a = $this->article();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('article-photos.store', $a), [
            'photos' => [
                UploadedFile::fake()->image('a.jpg', 3000, 2000),
                UploadedFile::fake()->image('b.png', 800, 600),
                UploadedFile::fake()->image('c.webp', 400, 400),
            ],
        ])->assertRedirect()->assertSessionHas('status', '3 fotos añadidas.');

        $photos = $a->photos()->get();
        $this->assertCount(3, $photos);
        $this->assertSame([0, 1, 2], $photos->pluck('position')->all());
        [$w, $h] = getimagesize(Storage::disk('public')->path($photos[0]->path));
        $this->assertSame([1600, 1067], [$w, $h]);
        [$tw] = getimagesize(Storage::disk('public')->path($photos[0]->thumb_path));
        $this->assertSame(480, $tw);
        $this->assertSame($photos[0]->thumbUrl(), $a->fresh()->photoUrl());

        // JSON (subida desde la ficha, de una en una)
        $this->actingAs($user)->postJson(route('article-photos.store', $a), ['photos' => [UploadedFile::fake()->image('d.jpg')]])
            ->assertOk()->assertJsonCount(1, 'photos');
        $this->assertSame(3, $a->photos()->max('position'));

        // Cambiar la principal y borrar
        $this->actingAs($user)->post(route('article-photos.main', $photos[2]))->assertRedirect();
        $this->assertSame($photos[2]->id, $a->fresh()->mainPhoto->id);
        $this->actingAs($user)->delete(route('article-photos.destroy', $photos[2]))->assertRedirect();
        Storage::disk('public')->assertMissing($photos[2]->path);
        $this->assertSame([0, 1, 2], $a->photos()->pluck('position')->all());

        $this->actingAs($user)->get(route('articles.show', $a))->assertOk()->assertSee('Añadir fotos');
        $this->actingAs($user)->get(route('articles.index'))->assertOk();
    }

    public function test_rejects_non_images_and_creates_article_with_photos(): void
    {
        Storage::fake('public');
        $a = $this->article();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('article-photos.store', $a), ['photos' => [UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')]])
            ->assertStatus(422);

        $this->actingAs($user)->post(route('articles.store'), [
            'category_id' => $a->category_id, 'name' => 'Mesa', 'rental_price' => 5, 'replacement_price' => 50, 'active' => 1,
            'photos' => [UploadedFile::fake()->image('x.jpg'), UploadedFile::fake()->image('y.jpg')],
        ])->assertRedirect();
        $this->assertSame(2, Article::where('name', 'Mesa')->first()->photos()->count());
    }
}
