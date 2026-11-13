@extends('layouts.portal')
@section('title', $article->name.' · alquiler en Málaga')
@section('description', \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) $article->description))) ?: $article->name.' en alquiler para bodas y eventos en Málaga'.($article->dimensions ? ' ('.$article->dimensions.')' : '').'. Colección '.$article->category->name.' de Viva la Virgen.', 158))
@if ($article->photos->isNotEmpty())@section('og_image', $article->photos->first()->url())@endif
@push('jsonld'){!! \App\Support\JsonLd::script(['@graph' => [
    array_filter([
        '@type' => 'Product',
        'name' => $article->name,
        'sku' => $article->reference,
        'description' => trim(strip_tags((string) $article->description)) ?: null,
        'category' => $article->category->name,
        'image' => $article->photos->map->url()->values()->all() ?: null,
        'brand' => ['@type' => 'Brand', 'name' => config('app.name')],
        'url' => route('portal.article', $article->reference),
    ]),
    \App\Support\JsonLd::breadcrumbs([['Inicio', route('portal.home')], [$article->category->name, route('portal.collection', $article->category->slug())], [$article->name, route('portal.article', $article->reference)]]),
]]) !!}@endpush
@section('full')
<div class="pt-section" style="padding-top:18px;padding-bottom:0"><a class="pt-back" href="{{ route('portal.collection', $article->category->slug()) }}">← {{ $article->category->name }}</a></div>
<div class="pt-detail">
    <div class="pt-gallery">
        @php($photos = $article->photos)
        @if ($photos->isNotEmpty())
            <div class="pt-gallery-main"><img id="pt-main" src="{{ $photos->first()->url() }}" alt="{{ $article->name }}">@if ($article->dimensions)<span class="pt-dim">{{ $article->dimensions }}</span>@endif</div>
            @if ($photos->count() > 1)
                <div class="pt-thumbs">
                    @foreach ($photos as $p)
                        <button type="button" data-src="{{ $p->url() }}" @class(['on' => $loop->first])><img src="{{ $p->thumbUrl() }}" alt=""></button>
                    @endforeach
                </div>
            @endif
        @else
            <div class="pt-gallery-main"><span class="pt-noimg"></span></div>
        @endif
    </div>
    <div class="pt-info">
        <div class="pt-kicker">Colección · {{ $article->category->name }}</div>
        <h1>{{ $article->name }}</h1>
        @if ($article->dimensions)<p class="pt-spec"><span>Medidas</span>{{ $article->dimensions }}</p>@endif
        @if ($article->is_bundle && $article->components->isNotEmpty())
            <div class="pt-spec"><span>Incluye</span>
                <ul style="margin:4px 0 0;padding-left:18px">
                @foreach ($article->components as $c)
                    <li>{{ $c->pivot->quantity }} × @if ($c->active)<a href="{{ route('portal.article', $c->reference) }}">{{ $c->name }}</a>@else{{ $c->name }}@endif</li>
                @endforeach
                </ul>
            </div>
        @endif
        <div class="pt-info-price">@include('portal._price', ['article' => $article])</div>
        @if ($blocked)
            <p class="pt-spec"><span>Disponibles</span>No disponible</p>
        @elseif ($availability !== null)
            <p class="pt-spec"><span>Disponibles</span>{{ $availability }} para la fecha de tu evento</p>
        @endif
        @unless ($blocked)
        <form method="post" action="{{ route('portal.cart.add', $article) }}" class="pt-addbig">
            @csrf
            <div class="pt-qty"><button type="button" data-d="-1">−</button><input type="number" name="quantity" value="1" min="1" max="10000" aria-label="Cantidad"><button type="button" data-d="1">+</button></div>
            <button class="pt-pill dark big">Añadir al pedido</button>
        </form>
        @endunless
        @if ($inCart)<p class="muted" style="font-size:14px">Ya tienes {{ $inCart }} en tu pedido · <a href="{{ route('portal.cart') }}">Ver pedido</a></p>@endif
        @if ($article->description)<div class="pt-desc">{!! nl2br(e($article->description)) !!}</div>@endif
    </div>
</div>
@if ($related->isNotEmpty())
    <section class="pt-section"><div class="pt-kicker">También en {{ $article->category->name }}</div></section>
    <div class="pt-products" data-cols="4">
        @foreach ($related as $a)
            <article class="pt-prod">
                <a class="pt-prod-img" href="{{ route('portal.article', $a->reference) }}">@if ($a->mainPhoto)<img src="{{ $a->mainPhoto->url() }}" alt="{{ $a->name }}" loading="lazy">@else<span class="pt-noimg"></span>@endif</a>
                <div class="pt-prod-body"><h3><a href="{{ route('portal.article', $a->reference) }}">{{ $a->name }}</a></h3>@include('portal._price', ['article' => $a])</div>
            </article>
        @endforeach
    </div>
@endif
@push('scripts')
<script>
document.querySelectorAll('.pt-thumbs button').forEach(b => b.addEventListener('click', () => {
    document.getElementById('pt-main').src = b.dataset.src;
    document.querySelectorAll('.pt-thumbs button').forEach(x => x.classList.toggle('on', x === b));
}));
document.querySelectorAll('.pt-qty button').forEach(b => b.addEventListener('click', () => {
    const i = b.parentNode.querySelector('input'); i.value = Math.max(1, (parseInt(i.value) || 1) + parseInt(b.dataset.d));
}));
</script>
@endpush
@endsection
