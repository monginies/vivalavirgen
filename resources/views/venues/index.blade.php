@extends('layouts.app')
@section('title', 'Fincas')
@section('content')
<div class="head">
    <div><h1>Fincas</h1><div class="sub">Lugares de celebración habituales. Al elegir uno en un pedido se rellenan la dirección y los km.</div></div>
    <a class="btn" href="{{ route('venues.create') }}">+ Nueva finca</a>
</div>
<form class="filters" method="get">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar por nombre, población o CP…">
    <button class="btn sec">Buscar</button>
</form>
<div class="card table-wrap">
    <table>
        <thead><tr><th>Nombre</th><th class="hide-sm">Dirección</th><th>Población</th><th class="num">Km (ida)</th><th class="num hide-sm">Pedidos</th><th></th></tr></thead>
        <tbody>
        @forelse ($venues as $v)
            <tr>
                <td class="grow"><a href="{{ route('venues.edit', $v) }}">{{ $v->name }}</a>@unless ($v->active) <span class="badge grey">Inactiva</span>@endunless</td>
                <td class="muted hide-sm">{{ $v->address ?: '—' }}</td>
                <td>{{ $v->place() ?: '—' }}</td>
                <td class="num" data-label="Km">{{ $v->distance_km !== null ? number_format($v->distance_km, 0, ',', '.') : '—' }}</td>
                <td class="num hide-sm">{{ $v->orders_count ?: '—' }}</td>
                <td class="actions" style="justify-content:flex-end"><a class="btn sec sm" href="{{ route('orders.create', ['venue' => $v->id]) }}">+ Pedido</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty">Aún no hay fincas guardadas. <a href="{{ route('venues.create') }}">Añade la primera</a>.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $venues->links('partials.pagination') }}
@endsection
