@extends('layouts.app')
@section('title', 'Expedición '.$order->number)
@section('content')
@php($S = \App\Enums\OrderStatus::class)
<div class="head">
    <div>
        <h1>Expedición <span class="mono">{{ $order->number }}</span> <span class="badge {{ $order->status->color() }}">{{ $order->status->label() }}</span></h1>
        <div class="sub">{{ $order->client->name }}@unless (auth()->user()->isLogistics()) · <a href="{{ route('orders.show', $order) }}">ver pedido</a>@endunless</div>
    </div>
    <div class="actions">
        @if ($order->status === $S::Paid || $order->status === $S::Delivered)
            <a @class(['btn', 'sec' => $order->status === $S::Delivered]) href="{{ route('scan.show', [$order, 'salida']) }}">{{ $order->status === $S::Paid ? 'Dar salida' : 'Seguir dando salida' }}</a>
        @endif
        @if ($order->status === $S::Delivered || $order->status === $S::Returned)
            <a @class(['btn', 'sec' => $order->status === $S::Returned]) href="{{ route('scan.show', [$order, 'entrada']) }}">{{ $order->status === $S::Delivered ? 'Dar entrada' : 'Revisar entrada' }}</a>
        @endif
        <a class="btn sec web-only" href="{{ route('orders.expedition', $order) }}" target="_blank">Hojas PDF</a>
    </div>
</div>

<div class="grid g2">
    <div class="card">
        <h2>Entrega y recogida</h2>
        <dl class="props">
            <dt>Entrega</dt><dd>{{ $order->start_date->isoFormat('dddd D/MM/YYYY') }}@if ($order->delivery_time) · {{ $order->delivery_time }}@endif</dd>
            @if ($order->event_date)<dt>Evento</dt><dd>{{ $order->event_date->isoFormat('dddd D/MM/YYYY') }}</dd>@endif
            <dt>Recogida</dt><dd>{{ $order->end_date->isoFormat('dddd D/MM/YYYY') }}@if ($order->pickup_time) · {{ $order->pickup_time }}@endif</dd>
            <dt>Camiones</dt><dd>{{ $order->trucks }}</dd>
        </dl>
    </div>
    <div class="card">
        <h2>Lugar y contacto</h2>
        <dl class="props">
            <dt>Lugar</dt><dd>{{ $order->destination() ?: 'Por confirmar' }}@if ($order->distance_km) <span class="muted">· {{ $order->distance_km }} km</span>@endif</dd>
            <dt>Contacto</dt><dd>{{ $order->site_contact_name ?: '—' }}@if ($order->site_contact_phone) · <a href="tel:{{ preg_replace('/[^0-9+]/', '', $order->site_contact_phone) }}">{{ $order->site_contact_phone }}</a>@endif</dd>
            @if ($order->logistics_notes)<dt>Notas</dt><dd>{!! nl2br(e($order->logistics_notes)) !!}</dd>@endif
        </dl>
    </div>
</div>

<div class="card table-wrap">
    <h2>Material</h2>
    <table>
        <thead><tr><th></th><th>Artículo</th><th class="num">Cant.</th></tr></thead>
        <tbody>
        @foreach ($rows as $r)
            <tr @if ($r['bundle']) style="background:var(--sage-soft)" @endif>
                <td style="width:44px">@if ($p = $r['article']->photoUrl())<img class="thumb" src="{{ $p }}" alt="">@else<div class="thumb"></div>@endif</td>
                <td>@if ($r['part'])<span class="muted">↳</span> @endif<span class="mono muted" style="font-size:12px">{{ $r['article']->reference }}</span> {{ $r['article']->name }}
                    @if ($r['bundle'])<span class="muted">(combinado: se carga por piezas)</span>@endif
                    @if ($r['article']->dimensions)<br><span class="muted" style="font-size:13px">{{ $r['article']->dimensions }}</span>@endif</td>
                <td class="num"><b>{{ $r['quantity'] }}</b></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
