@extends('layouts.portal')
@section('title', 'Pedido '.$order->number)
@section('content')
@php($money = fn ($v) => \App\Support\Money::eur($v))
<p style="margin:0 0 14px"><a href="{{ route('portal.account') }}">← Mis pedidos</a></p>
<div class="head"><div><h1>Pedido <span class="mono">{{ $order->number }}</span> <span class="badge {{ $order->status->color() }}" style="vertical-align:middle">{{ $order->status->label() }}</span></h1>
    <div class="sub">Solicitado el {{ $order->created_at->format('d/m/Y') }}</div></div>
    @if ($order->proforma_sent_at)<a class="btn web-only" href="{{ route('portal.orders.proforma', $order->number) }}">Descargar proforma (PDF)</a>@endif
</div>
@if ($order->status->isExpirable() && $order->expires_at)
    <div class="flash ok">Material pre-reservado hasta el {{ $order->expires_at->format('d/m/Y H:i') }}. En breve recibirás una proforma con los datos para realizar el pago.</div>
@endif
<div class="grid g2">
    <div class="card">
        <h2>Evento</h2>
        <dl class="props">
            @if ($order->event_date)<dt>Fecha</dt><dd>{{ $order->event_date->isoFormat('dddd D [de] MMMM [de] YYYY') }}</dd>@endif
            <dt>Entrega</dt><dd>{{ $order->start_date->format('d/m/Y') }}</dd>
            <dt>Recogida</dt><dd>{{ $order->end_date->format('d/m/Y') }}</dd>
            <dt>Lugar</dt><dd>{{ $order->destination() ?: '—' }}</dd>
        </dl>
    </div>
    <div class="card">
        <h2>Importe</h2>
        @include('partials.order-totals', ['order' => $order])
    </div>
</div>
<div class="card table-wrap">
    <h2>Material</h2>
    <table>
        <thead><tr><th colspan="2">Artículo</th><th class="num">Cantidad</th><th class="num">Precio ud.</th><th class="num">Importe</th></tr></thead>
        <tbody>
        @foreach ($order->lines as $l)
            <tr>
                <td style="width:56px">@if ($l->article->mainPhoto)<img class="thumb" src="{{ $l->article->mainPhoto->thumbUrl() }}" alt="">@endif</td>
                <td>{{ $l->article->name }}@if ($inc = $l->article->componentsSummary())<br><span class="muted" style="font-size:13px">Incluye: {{ $inc }}</span>@endif</td>
                <td class="num">{{ $l->quantity }}</td>
                <td class="num">{{ $money($l->unit_price) }}</td>
                <td class="num">{{ $money($l->total()) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
