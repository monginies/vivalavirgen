@extends('layouts.portal')
@push('jsonld'){!! \App\Support\JsonLd::script(\App\Support\JsonLd::business()) !!}@endpush
@section('full')
@php($heroImg = \App\Models\Setting::get('hero_image'))
<section class="pt-hero @if ($heroImg) has-img @endif" @if ($heroImg) style="background-image:url('{{ asset('storage/'.$heroImg) }}')" @endif>
    <div class="pt-hero-in">
        <div class="pt-kicker light">Viva la Virgen · Alquiler y decoración mediterránea</div>
        <h1>{{ \App\Models\Setting::get('hero_title') }}</h1>
        <a class="pt-hero-link" href="#colecciones">Descubre nuestras colecciones ↓</a>
    </div>
</section>
@include('portal._toolbar', ['current' => null])
<div class="pt-collections" id="colecciones">
    @forelse ($categories as $c)
        @php($cover = $c->coverUrl())
        <a class="pt-coll" href="{{ route('portal.collection', $c->slug()) }}">
            <span class="pt-coll-img">@if ($cover)<img src="{{ $cover }}" alt="" loading="{{ $loop->index < 2 ? 'eager' : 'lazy' }}">@endif</span>
            <span class="pt-coll-foot"><span class="pt-coll-name">{{ $c->name }}</span><span class="pt-coll-cta">{{ $c->articles_count ? $c->articles_count.' '.($c->articles_count == 1 ? 'artículo' : 'artículos').' ↗' : 'Próximamente' }}</span></span>
        </a>
    @empty
        <p class="empty" style="grid-column:1/-1">Pronto publicaremos nuestro catálogo.</p>
    @endforelse
</div>
@push('scripts')
<script>
// Las colecciones aparecen con un ligero ascenso al llegar a ellas, una tras otra
(() => {
    if (matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) return;
    const cards = [...document.querySelectorAll('.pt-coll')];
    if (!cards.length) return;
    document.documentElement.classList.add('pt-motion');
    const io = new IntersectionObserver(entries => entries.forEach(e => {
        if (e.isIntersecting) { e.target.classList.add('pt-in'); io.unobserve(e.target); }
    }), {threshold: 0, rootMargin: '0px 0px -24px 0px'});
    cards.forEach((card, i) => { card.style.setProperty('--pt-delay', (Math.min(i % 7, 6) * 32) + 'ms'); io.observe(card); });
})();
</script>
@endpush
@endsection
