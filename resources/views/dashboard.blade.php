@extends('layouts.app')
@section('content')
<div class="head"><h1>Inicio</h1>
    <div class="actions">
        <a class="btn" href="{{ route('orders.create') }}">+ Pedido</a>
        <a class="btn sec" href="{{ route('articles.create') }}">+ Artículo</a>
        <a class="btn sec" href="{{ route('clients.create') }}">+ Cliente</a>
    </div>
</div>
<div class="grid g4" style="margin-bottom:20px">
    <a class="card" style="text-decoration:none;color:inherit;margin:0" href="{{ route('articles.index') }}"><div class="stat">{{ $articles }}</div><div class="stat-label">Artículos</div></a>
    <div class="card" style="margin:0"><div class="stat">{{ $units }}</div><div class="stat-label">Unidades con QR</div></div>
    <a class="card" style="text-decoration:none;color:inherit;margin:0" href="{{ route('categories.index') }}"><div class="stat">{{ $categories }}</div><div class="stat-label">Categorías</div></a>
    <a class="card" style="text-decoration:none;color:inherit;margin:0" href="{{ route('clients.index') }}"><div class="stat">{{ $clients }}</div><div class="stat-label">Clientes</div></a>
</div>
<div class="card table-wrap">
    <div class="head" style="margin-bottom:8px"><h2 style="margin:0">Próximos pedidos</h2>
        @if ($pendingOrders)<a class="badge amber" href="{{ route('orders.index') }}">{{ $pendingOrders }} pre-reserva{{ $pendingOrders > 1 ? 's' : '' }} sin pagar</a>@endif</div>
    <table>
        @forelse ($upcomingOrders as $o)
            <tr>
                <td class="mono"><a href="{{ route('orders.show', $o) }}">{{ $o->number }}</a></td>
                <td>{{ $o->client->name }}</td>
                <td>{{ $o->start_date->format('d/m') }} – {{ $o->end_date->format('d/m') }}</td>
                <td><span class="badge {{ $o->status->color() }}">{{ $o->status->label() }}</span></td>
            </tr>
        @empty
            <tr><td class="empty">No hay pedidos próximos. <a href="{{ route('orders.create') }}">Crea el primero</a>.</td></tr>
        @endforelse
    </table>
</div>
<div class="grid g2">
    <div class="card">
        <h2>Estado de las unidades</h2>
        <table>
            @foreach ($unitsByStatus as $row)
                <tr><td><span class="badge {{ $row['status']->color() }}">{{ $row['status']->label() }}</span></td><td class="num">{{ $row['total'] }}</td></tr>
            @endforeach
        </table>
    </div>
    <div class="card">
        <h2>Últimos artículos</h2>
        @forelse ($recentArticles as $a)
            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--line)">
                <a href="{{ route('articles.show', $a) }}">{{ $a->name }}</a>
                <span class="muted">{{ $a->units_count }} ud.</span>
            </div>
        @empty
            <p class="muted">Aún no hay artículos. <a href="{{ route('articles.create') }}">Crea el primero</a>.</p>
        @endforelse
    </div>
</div>
@endsection
