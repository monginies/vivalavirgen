<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ArticlePhoto;
use App\Support\ImageResizer;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ArticlePhotoController extends Controller
{
    /** Sube una o varias fotos. Desde la ficha se envían de una en una (ya reducidas en el navegador). */
    public function store(Request $request, Article $article)
    {
        $request->validate([
            'photos' => ['required', 'array', 'max:20'],
            'photos.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:20480'],
        ], [
            'photos.required' => 'Elige al menos una foto.',
            'photos.*.mimes' => 'Formato no admitido. Usa JPG, PNG o WebP.',
            'photos.*.max' => 'Cada foto puede pesar como máximo 20 MB.',
        ]);

        try {
            $photos = self::attach($article, $request->file('photos'));
        } catch (RuntimeException $e) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 422)
                : back()->with('error', $e->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json(['photos' => collect($photos)->map(fn ($p) => self::json($p))]);
        }

        return back()->with('status', count($photos) === 1 ? 'Foto añadida.' : count($photos).' fotos añadidas.');
    }

    public function destroy(Request $request, ArticlePhoto $photo)
    {
        $article = $photo->article;
        $photo->delete();
        self::renumber($article);

        return $request->expectsJson() ? response()->noContent() : back()->with('status', 'Foto eliminada.');
    }

    /** La convierte en la foto principal (la que sale en listados). */
    public function main(Request $request, ArticlePhoto $photo)
    {
        DB::transaction(function () use ($photo) {
            $photo->article->photos()->whereKeyNot($photo->id)->increment('position');
            $photo->update(['position' => 0]);
            self::renumber($photo->article);
        });

        return back()->with('status', 'Foto principal cambiada.');
    }

    /** @param  array<UploadedFile>  $files */
    public static function attach(Article $article, array $files): array
    {
        $next = (int) $article->photos()->max('position') + ($article->photos()->exists() ? 1 : 0);
        $photos = [];
        foreach ($files as $file) {
            $photos[] = $article->photos()->create(ImageResizer::store($file, 'articles/'.$article->id) + ['position' => $next++]);
        }

        return $photos;
    }

    private static function renumber(Article $article): void
    {
        foreach ($article->photos()->get() as $i => $p) {
            if ($p->position !== $i) {
                $p->update(['position' => $i]);
            }
        }
    }

    private static function json(ArticlePhoto $p): array
    {
        return [
            'id' => $p->id,
            'url' => $p->url(),
            'thumb' => $p->thumbUrl(),
            'delete' => route('article-photos.destroy', $p),
            'main' => route('article-photos.main', $p),
        ];
    }
}
