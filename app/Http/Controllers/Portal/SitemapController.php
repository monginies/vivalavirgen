<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Category;

/** Mapa del sitio para Google: portada, colecciones y artículos publicados. */
class SitemapController extends Controller
{
    public function __invoke()
    {
        $urls = collect([['loc' => route('portal.home'), 'lastmod' => null]]);
        foreach (Category::forPortal()->get() as $c) {
            $urls->push(['loc' => route('portal.collection', $c->slug()), 'lastmod' => $c->updated_at]);
        }
        foreach (Article::where('active', true)->get(['reference', 'updated_at']) as $a) {
            $urls->push(['loc' => route('portal.article', $a->reference), 'lastmod' => $a->updated_at]);
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as $u) {
            $xml .= '<url><loc>'.e($u['loc']).'</loc>'.($u['lastmod'] ? '<lastmod>'.$u['lastmod']->toDateString().'</lastmod>' : '').'</url>'."\n";
        }

        return response($xml.'</urlset>', 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
