@extends('layouts.app')
@section('title', 'Pedidos')
@section('content')
<div class="head"><h1>Pedidos</h1><a class="btn" href="{{ route('orders.create') }}">+ Nuevo pedido</a></div>
<form class="filters" method="get">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar por número o cliente…">
    <select name="status" onchange="this.form.submit()">
        <option value="">En curso</option>
        @foreach (\App\Enums\OrderStatus::cases() as $s)
            <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
        @endforeach
    </select>
    <label class="check"><input type="checkbox" name="all" value="1" @checked(request('all')) onchange="this.form.submit()"> Ver todos</label>
    <button class="btn sec">Filtrar</button>
</form>
<div class="card table-wrap">
    <table>
        <thead><tr><th>Número</th><th>Cliente</th><th>Entrega</th><th class="hide-sm">Recogida</th><th class="hide-sm">Destino</th><th>Estado</th><th class="num">Total</th></tr></thead>
        <tbody>
        @forelse ($orders as $o)
            <tr>
                <td class="mono"><a href="{{ route('orders.show', $o) }}">{{ $o->number }}</a>@if ($o->source === 'web') <span class="badge blue">Web</span>@endif</td>
                <td class="grow">{{ $o->client->name }}</td>
                <td data-label="Entrega">{{ $o->start_date->format('d/m/Y') }}</td>
                <td class="hide-sm">{{ $o->end_date->format('d/m/Y') }}</td>
                <td class="muted hide-sm">{{ $o->destination() ?: '—' }}</td>
                <td>
                    <span class="badge {{ $o->status->color() }}">{{ $o->status->label() }}</span>
                    @if ($o->status->isExpirable() && $o->expires_at)
                        <div class="hint">caduca {{ $o->expires_at->format('d/m H:i') }}</div>
                    @endif
                </td>
                <td class="num end">{{ \App\Support\Money::eur($o->total()) }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="empty">No hay pedidos que coincidan.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $orders->links('partials.pagination') }}
@endsection
