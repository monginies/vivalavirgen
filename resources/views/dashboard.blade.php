@extends('layouts.app')
@section('content')
<div class="head"><h1>Inicio</h1>
    <div class="actions">
        <a class="btn" href="{{ route('orders.create') }}">+ Pedido</a>
        <a class="btn sec" href="{{ route('articles.create') }}">+ Artículo</a>
        <a class="btn sec" href="{{ route('clients.create') }}">+ Cliente</a>
    </div>
</div>
@if ($toApprove->isNotEmpty())
    <div class="card table-wrap" style="border:2px solid var(--amber);background:#fdf6ea">
        <div class="head" style="margin-bottom:8px">
            <h2 style="margin:0;color:var(--amber)">{{ $toApprove->count() }} {{ $toApprove->count() > 1 ? 'pedidos pendientes' : 'pedido pendiente' }} de aprobar</h2>
            <a class="btn sm" href="{{ route('orders.index', ['status' => 'requested']) }}">Ver todos</a>
        </div>
        <table>
            @foreach ($toApprove as $o)
                @php($urgent = $o->expires_at && $o->expires_at->diffInHours(now(), true) < 24)
                <tr>
                    <td class="mono"><a href="{{ route('orders.show', $o) }}">{{ $o->number }}</a>@if ($o->source === 'web') <span class="badge blue">Web</span>@endif</td>
                    <td class="grow">{{ $o->client->name }}</td>
                    <td class="hide-sm">{{ $o->event_date ? 'Evento '.$o->event_date->format('d/m/Y') : $o->start_date->format('d/m').' – '.$o->end_date->format('d/m') }}</td>
                    <td class="num">{{ \App\Support\Money::eur($o->total()) }}</td>
                    <td>@if ($o->expires_at)<span class="badge {{ $urgent ? 'red' : 'amber' }}">Caduca {{ $o->expires_at->diffForHumans() }}</span>@endif</td>
                    <td class="actions" style="justify-content:flex-end"><a class="btn sm" href="{{ route('orders.show', $o) }}">Revisar</a></td>
                </tr>
            @endforeach
        </table>
    </div>
@endif
@if ($toSend->isNotEmpty())
    <div class="card table-wrap" style="border:2px solid var(--amber);background:#fdf6ea">
        <div class="head" style="margin-bottom:4px">
            <h2 style="margin:0;color:var(--amber)">{{ $toSend->count() }} {{ $toSend->count() > 1 ? 'pedidos aceptados' : 'pedido aceptado' }} con la proforma sin enviar</h2>
        </div>
        <p class="muted" style="margin:0 0 8px;font-size:14px">Envía la proforma para que el cliente pueda pagar antes de que caduque la pre-reserva.</p>
        <table>
            @foreach ($toSend as $o)
                @php($urgent = $o->expires_at && $o->expires_at->diffInHours(now(), true) < 24)
                <tr>
                    <td class="mono"><a href="{{ route('orders.show', $o) }}">{{ $o->number }}</a></td>
                    <td class="grow">{{ $o->client->name }}</td>
                    <td class="num">{{ \App\Support\Money::eur($o->amountDue()) }}</td>
                    <td>@if ($o->expires_at)<span class="badge {{ $urgent ? 'red' : 'amber' }}">Caduca {{ $o->expires_at->diffForHumans() }}</span>@endif</td>
                    <td class="actions" style="justify-content:flex-end"><a class="btn sm" href="{{ route('orders.show', $o) }}#proforma">Enviar proforma</a></td>
                </tr>
            @endforeach
        </table>
    </div>
@endif
@if ($toPay->isNotEmpty())
    <div class="card table-wrap" style="border:2px solid var(--red);background:#fbefea">
        <div class="head" style="margin-bottom:4px">
            <h2 style="margin:0;color:var(--red)">{{ $toPay->count() }} {{ $toPay->count() > 1 ? 'pedidos' : 'pedido' }} con la proforma enviada, pendiente{{ $toPay->count() > 1 ? 's' : '' }} de pago</h2>
        </div>
        <p class="muted" style="margin:0 0 8px;font-size:14px">Si no se pagan antes de que caduque la pre-reserva, el pedido se cancela y el material queda libre.</p>
        <table>
            @foreach ($toPay as $o)
                @php($urgent = $o->expires_at && $o->expires_at->diffInHours(now(), true) < 24)
                <tr>
                    <td class="mono"><a href="{{ route('orders.show', $o) }}">{{ $o->number }}</a></td>
                    <td class="grow">{{ $o->client->name }}</td>
                    <td><span class="badge {{ $o->status->color() }}">{{ $o->status->label() }}</span>@if ($o->proforma_sent_at)<br><span class="muted" style="font-size:13px">enviada {{ $o->proforma_sent_at->format('d/m H:i') }}</span>@endif</td>
                    <td class="num">{{ \App\Support\Money::eur($o->amountDue()) }}</td>
                    <td>@if ($o->expires_at)<span class="badge {{ $urgent ? 'red' : 'amber' }}">Caduca {{ $o->expires_at->diffForHumans() }}</span><br><span class="muted" style="font-size:13px">{{ $o->expires_at->format('d/m H:i') }}</span>@endif</td>
                    <td class="actions" style="justify-content:flex-end">
                        <a class="btn sm" href="{{ route('orders.show', $o) }}">Revisar</a>
                    </td>
                </tr>
            @endforeach
        </table>
    </div>
@endif
@if ($pendingClients)
    <div class="flash ko"><b>{{ $pendingClients }}</b> cliente{{ $pendingClients > 1 ? 's' : '' }} de la web pendiente{{ $pendingClients > 1 ? 's' : '' }} de aprobar. <a href="{{ route('clients.index', ['pendientes' => 1]) }}">Revisar</a></div>
@endif
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
                <td class="grow">{{ $o->client->name }}</td>
                <td>{{ $o->start_date->format('d/m') }} – {{ $o->end_date->format('d/m') }}</td>
                <td><span class="badge {{ $o->status->color() }}">{{ $o->status->label() }}</span></td>
            </tr>
        @empty
            <tr><td class="empty">No hay pedidos próximos. <a href="{{ route('orders.create') }}">Crea el primero</a>.</td></tr>
        @endforelse
    </table>
</div>
@include('partials.push')
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
