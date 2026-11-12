@extends('layouts.portal')
@section('content')
@unless (request('q') || $category || request('page') > 1)
    <section class="pt-hero">
        <h1>Mobiliario y decoración para tu celebración</h1>
        <p>Elige las piezas, indica la fecha del evento y te lo llevamos. Bodas, comuniones y eventos en Málaga y alrededores.</p>
    </section>
@endunless
<form class="pt-filters" method="get">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar sillas, mesas, faroles…" aria-label="Buscar">
    @if ($category)<input type="hidden" name="categoria" value="{{ $category->code }}">@endif
    <button class="btn">Buscar</button>
</form>
<div class="pt-chips">
    <a href="{{ route('portal.home', array_filter(['q' => request('q')])) }}" @class(['chip', 'on' => ! $category])>Todo</a>
    @foreach ($categories as $c)
        <a href="{{ route('portal.home', array_filter(['categoria' => $c->code, 'q' => request('q')])) }}" @class(['chip', 'on' => $category?->is($c)])>{{ $c->name }}</a>
    @endforeach
</div>
@if ($period = \App\Support\Cart::period())
    <p class="muted" style="font-size:14px">Mostrando unidades libres para tu fecha: {{ \Carbon\Carbon::parse($period[0])->format('d/m') }} – {{ \Carbon\Carbon::parse($period[1])->format('d/m/Y') }}. <a href="{{ route('portal.cart') }}">Cambiar</a></p>
@endif
<div class="pt-grid">
    @forelse ($articles as $a)
        <article class="pt-card">
            <a class="pt-img" href="{{ route('portal.article', $a->reference) }}">
                @if ($a->mainPhoto)<img src="{{ $a->mainPhoto->thumbUrl() }}" alt="{{ $a->name }}" loading="lazy">@else<span class="pt-noimg"></span>@endif
            </a>
            <div class="pt-body">
                <div class="muted" style="font-size:12px;text-transform:uppercase;letter-spacing:.05em">{{ $a->category->name }}</div>
                <h3><a href="{{ route('portal.article', $a->reference) }}">{{ $a->name }}</a></h3>
                @if ($a->dimensions)<div class="muted" style="font-size:13px">{{ $a->dimensions }}</div>@endif
                <div class="pt-row">
                    <div>@include('portal._price', ['article' => $a])</div>
                    @isset($availability[$a->id])<span class="badge {{ $availability[$a->id] ? 'green' : 'grey' }}">{{ $availability[$a->id] }} libres</span>@endisset
                </div>
                <form method="post" action="{{ route('portal.cart.add', $a) }}" class="pt-add">
                    @csrf
                    <input type="number" name="quantity" value="1" min="1" max="10000" aria-label="Cantidad">
                    <button class="btn sm">Añadir</button>
                </form>
            </div>
        </article>
    @empty
        <p class="empty" style="grid-column:1/-1">No hay artículos que coincidan con la búsqueda.</p>
    @endforelse
</div>
{{ $articles->links('partials.pagination') }}
@endsection
