@extends('layouts.app')
@section('title', $client->name)
@section('content')
<div class="head">
    <div><h1>{{ $client->name }}</h1><div class="sub">{{ $client->typeLabel() }}@if ($client->tax_id) · <span class="mono">{{ $client->tax_id }}</span>@endif</div></div>
    <a class="btn" href="{{ route('clients.edit', $client) }}">Editar</a>
</div>
<div class="grid g2">
    <div class="card">
        <h2>Contacto</h2>
        <dl class="props">
            <dt>Contacto</dt><dd>{{ $client->contact_name ?: '—' }}</dd>
            <dt>Teléfono</dt><dd>@if ($client->phone)<a href="tel:{{ $client->phone }}">{{ $client->phone }}</a>@else — @endif</dd>
            <dt>Email</dt><dd>@if ($client->email)<a href="mailto:{{ $client->email }}">{{ $client->email }}</a>@else — @endif</dd>
            <dt>Dirección</dt><dd>{{ collect([$client->address, trim($client->postal_code.' '.$client->city), $client->province])->filter()->join(', ') ?: '—' }}</dd>
        </dl>
    </div>
    <div class="card">
        <h2>Notas</h2>
        <p>{!! $client->notes ? nl2br(e($client->notes)) : '<span class="muted">Sin notas.</span>' !!}</p>
    </div>
</div>
<div class="card table-wrap">
    <div class="head" style="margin-bottom:8px"><h2 style="margin:0">Pedidos</h2>
        <a class="btn sm" href="{{ route('orders.create', ['client' => $client->id]) }}">+ Nuevo pedido</a></div>
    @php($orders = $client->orders()->with('lines')->orderByDesc('start_date')->get())
    <table>
        <thead><tr><th>Número</th><th>Entrega</th><th>Recogida</th><th>Estado</th><th class="num">Total</th></tr></thead>
        <tbody>
        @forelse ($orders as $o)
            <tr>
                <td class="mono"><a href="{{ route('orders.show', $o) }}">{{ $o->number }}</a></td>
                <td>{{ $o->start_date->format('d/m/Y') }}</td>
                <td>{{ $o->end_date->format('d/m/Y') }}</td>
                <td><span class="badge {{ $o->status->color() }}">{{ $o->status->label() }}</span></td>
                <td class="num">{{ \App\Support\Money::eur($o->total()) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty">Este cliente aún no tiene pedidos.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
