@extends('layouts.portal')
@section('title', $title)
@section('full')
<section class="pt-section pt-coll-head">
    <div class="pt-kicker">{{ $category ? 'Colección · '.now()->year : 'Búsqueda' }}</div>
    <h1>{{ $title }}</h1>
    <p class="muted">{{ $category?->description ?: ($category ? 'Descubre las piezas de esta colección. Entra en cada artículo para ver sus fotos, medidas y características.' : 'Resultados para «'.request('q').'».') }}</p>
    <div class="pt-meta">
        <span><b>{{ $articles->total() }}</b> {{ $articles->total() === 1 ? 'artículo' : 'artículos' }}</span>
        <span class="pt-view" aria-label="Columnas">Vista <button type="button" data-cols="1">1</button><button type="button" data-cols="2">2</button><button type="button" data-cols="3">3</button></span>
    </div>
</section>
@include('portal._toolbar', ['current' => $category])
<div class="pt-section" style="padding-top:14px;padding-bottom:14px"><a class="pt-back" href="{{ route('portal.home') }}#colecciones">← Todas las categorías</a></div>
<div class="pt-products" id="products" data-cols="3">
    @forelse ($articles as $a)
        <article class="pt-prod">
            <a class="pt-prod-img" href="{{ route('portal.article', $a->reference) }}">
                @if ($a->mainPhoto)<img src="{{ $a->mainPhoto->url() }}" alt="{{ $a->name }}" loading="lazy">@else<span class="pt-noimg"></span>@endif
                @if ($a->dimensions)<span class="pt-dim">{{ $a->dimensions }}</span>@endif
                @isset($availability[$a->id])<span class="pt-free {{ $availability[$a->id] ? '' : 'none' }}">{{ $availability[$a->id] }} libres</span>@endisset
            </a>
            <div class="pt-prod-body">
                <h3><a href="{{ route('portal.article', $a->reference) }}">{{ $a->name }}</a></h3>
                @if ($a->description)<p class="pt-prod-desc">{{ \Illuminate\Support\Str::limit(strtok($a->description, "\n"), 70) }}</p>@endif
                <div class="pt-prod-foot">
                    @include('portal._price', ['article' => $a])
                    <form method="post" action="{{ route('portal.cart.add', $a) }}">@csrf<input type="hidden" name="quantity" value="1"><button class="pt-plus" title="Añadir 1 al pedido" aria-label="Añadir al pedido">+</button></form>
                </div>
            </div>
        </article>
    @empty
        <p class="empty" style="grid-column:1/-1">No hay artículos que coincidan.</p>
    @endforelse
</div>
<div class="pt-section">{{ $articles->links('partials.pagination') }}</div>
@push('scripts')
<script>
(() => {
    const grid = document.getElementById('products'), btns = document.querySelectorAll('.pt-view button');
    const set = n => { grid.dataset.cols = n; btns.forEach(b => b.classList.toggle('on', b.dataset.cols == n)); try { localStorage.setItem('vlv-cols', n); } catch (e) {} };
    let saved = null; try { saved = localStorage.getItem('vlv-cols'); } catch (e) {}
    set(saved || (innerWidth < 640 ? 1 : 3));
    btns.forEach(b => b.addEventListener('click', () => set(b.dataset.cols)));
})();
</script>
@endpush
@endsection
