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
        <p class="muted" style="font-size:13px">Los presupuestos y alquileres del cliente aparecerán aquí en la siguiente fase.</p>
    </div>
</div>
@endsection
