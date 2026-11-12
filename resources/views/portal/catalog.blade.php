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
<div class="pt-collections" id="colecciones">
    @forelse ($categories as $c)
        @php($cover = $c->coverUrl())
        <a class="pt-coll" href="{{ route('portal.collection', $c->slug()) }}" @if ($cover) style="background-image:url('{{ $cover }}')" @endif>
            <span class="pt-coll-foot"><span class="pt-coll-name">{{ $c->name }}</span><span class="pt-coll-cta">{{ $c->articles_count ? 'Ver artículos ↗' : 'Próximamente' }}</span></span>
        </a>
    @empty
        <p class="empty" style="grid-column:1/-1">Pronto publicaremos nuestro catálogo.</p>
    @endforelse
</div>
@endsection
