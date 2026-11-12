@extends('layouts.portal')
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
<section class="pt-section" id="colecciones">
    <div class="pt-section-head">
        <div class="pt-kicker">Colecciones · {{ now()->year }}</div>
        <h2>Explora por categoría</h2>
        <p class="muted">{{ $categories->count() }} {{ $categories->count() === 1 ? 'categoría' : 'categorías' }} · {{ $total }} artículos</p>
    </div>
</section>
<div class="pt-collections">
    @forelse ($categories as $c)
        @php($cover = $c->coverUrl())
        <a class="pt-coll" href="{{ route('portal.collection', $c->slug()) }}" @if ($cover) style="background-image:url('{{ $cover }}')" @endif>
            <span class="pt-kicker light">Colección · {{ now()->year }}</span>
            <span class="pt-coll-foot"><span class="pt-coll-name">{{ $c->name }}</span><span class="pt-coll-cta">Ver artículos ↗</span></span>
        </a>
    @empty
        <p class="empty" style="grid-column:1/-1">Pronto publicaremos nuestro catálogo.</p>
    @endforelse
</div>
@endsection
