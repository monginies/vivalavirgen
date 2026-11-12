@extends('layouts.portal')
@section('title', 'Mi cuenta')
@section('content')
<div class="head"><div><h1>Hola, {{ $client->contact_name ?: $client->name }}</h1><div class="sub">{{ $client->name }}@if ($client->tax_id) · {{ $client->tax_id }}@endif · {{ $client->email }}</div>
    @if ($client->isApproved() && $client->category)<div style="margin-top:10px">Tu nivel de cliente: @include('partials.tier', ['category' => $client->category, 'discount' => true])</div>@endif</div>
    <div class="actions">
        <a class="btn" href="{{ route('portal.home') }}">Ir al catálogo</a>
        <form method="post" action="{{ route('portal.logout') }}" style="margin:0">@csrf<button class="btn sec">Cerrar sesión</button></form>
    </div></div>
<div class="card table-wrap">
    <h2>Mis pedidos</h2>
    <table>
        <thead><tr><th>Pedido</th><th>Fechas</th><th>Lugar</th><th>Estado</th><th class="num">Total</th></tr></thead>
        <tbody>
        @forelse ($orders as $o)
            <tr>
                <td class="mono"><a href="{{ route('portal.orders.show', $o->number) }}">{{ $o->number }}</a></td>
                <td>{{ $o->start_date->format('d/m') }} – {{ $o->end_date->format('d/m/Y') }}</td>
                <td class="muted">{{ $o->destination() ?: '—' }}</td>
                <td><span class="badge {{ $o->status->color() }}">{{ $o->status->label() }}</span></td>
                <td class="num">{{ \App\Support\Money::eur($o->total()) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty">Aún no has hecho ningún pedido.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<p class="muted" style="font-size:14px">¿Necesitas cambiar tus datos? Escríbenos a <a href="mailto:gestion@vivalavirgen.net">gestion@vivalavirgen.net</a>.</p>
@endsection
