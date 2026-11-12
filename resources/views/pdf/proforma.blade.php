<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Proforma {{ $order->number }}</title>
<style>
    @page { margin: 26mm 18mm 22mm 18mm; }
    * { font-family: "DejaVu Sans", sans-serif; }
    body { font-size: 9.5pt; color: #2e3028; line-height: 1.45; }
    .serif { font-family: "DejaVu Serif", serif; }
    .muted { color: #6c6d61; }
    .head { width: 100%; border-bottom: 2px solid #9a9b88; padding-bottom: 10px; margin-bottom: 18px; }
    .head td { vertical-align: top; }
    .logo { width: 70px; height: 70px; }
    h1 { font-family: "DejaVu Serif", serif; font-weight: normal; font-size: 22pt; margin: 0; letter-spacing: 1px; }
    .tag { font-size: 7.5pt; letter-spacing: 1.5px; text-transform: uppercase; color: #6c6d61; }
    .boxes { width: 100%; margin-bottom: 18px; border-collapse: separate; border-spacing: 0; }
    .boxes td { width: 50%; vertical-align: top; padding: 10px 12px; background: #f5f3ec; }
    .boxes td + td { border-left: 8px solid #fff; }
    .label { font-size: 7pt; letter-spacing: 1.2px; text-transform: uppercase; color: #6c6d61; margin-bottom: 3px; }
    table.lines { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    table.lines th { font-size: 7pt; letter-spacing: 1px; text-transform: uppercase; color: #6c6d61; text-align: left; border-bottom: 1px solid #9a9b88; padding: 6px 5px; font-weight: normal; }
    table.lines td { padding: 6px 5px; border-bottom: 1px solid #e7e4d8; }
    .num, table.lines th.num { text-align: right; white-space: nowrap; }
    .totals { width: 52%; margin-left: 48%; border-collapse: collapse; }
    .totals td { padding: 4px 5px; }
    .totals .sub td { border-top: 1px solid #9a9b88; }
    .totals .grand td { border-top: 2px solid #2e3028; font-size: 12pt; font-weight: bold; padding-top: 7px; }
    .deposit { margin-top: 8px; padding: 8px 12px; border: 1px solid #d3d0c2; }
    .pay { margin-top: 18px; padding: 10px 12px; background: #5d604e; color: #fff; }
    .pay .label { color: #e7e4d8; }
    .terms { margin-top: 16px; font-size: 8.5pt; color: #4a4c3d; }
    .foot { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 7pt; color: #8a8b7c; text-align: center; }
</style>
</head>
<body>
@php($eur = fn ($v) => number_format((float) $v, 2, ',', '.').' €')
@php($pct = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ','))
<div class="foot">Viva la Virgen · {{ collect([$company['name'], $company['tax_id'] ? 'NIF '.$company['tax_id'] : null, $company['address']])->filter()->join(' · ') }} — Documento proforma sin validez fiscal</div>

<table class="head"><tr>
    <td style="width:80px"><img class="logo" src="{{ public_path('img/logo-320.png') }}" alt=""></td>
    <td>
        <div class="serif" style="font-size:15pt">Viva la Virgen</div>
        <div class="muted">{{ $company['name'] }}@if ($company['tax_id']) · NIF {{ $company['tax_id'] }}@endif<br>{{ $company['address'] }}<br>
            {{ collect([$company['phone'], $company['email']])->filter()->join(' · ') }}</div>
    </td>
    <td style="text-align:right">
        <h1>PROFORMA</h1>
        <div class="tag">Alquiler decoración mediterránea</div>
        <div style="margin-top:8px"><b>{{ $order->number }}</b><br>
            <span class="muted">Fecha:</span> {{ now()->format('d/m/Y') }}<br>
            @if ($order->expires_at && $order->status->isExpirable())<span class="muted">Válida hasta:</span> {{ $order->expires_at->format('d/m/Y') }}@endif
        </div>
    </td>
</tr></table>

<table class="boxes"><tr>
    <td>
        <div class="label">Cliente</div>
        @php($c = $order->client)
        <b>{{ $c->name }}</b><br>
        @if ($c->tax_id){{ $c->tax_id }}<br>@endif
        @if ($addr = collect([$c->address, trim($c->postal_code.' '.$c->city), $c->province])->filter()->join(', ')){{ $addr }}<br>@endif
        <span class="muted">{{ collect([$c->contact_name, $c->phone, $c->email])->filter()->join(' · ') }}</span>
    </td>
    <td>
        <div class="label">Evento</div>
        @if ($order->event_date)<b>{{ $order->event_date->isoFormat('dddd D [de] MMMM [de] YYYY') }}</b><br>@endif
        Entrega {{ $order->start_date->format('d/m/Y') }} · Recogida {{ $order->end_date->format('d/m/Y') }}<br>
        {{ $order->destination() ?: 'Lugar por confirmar' }}
    </td>
</tr></table>

<table class="lines">
    <thead><tr><th style="width:70px">Ref.</th><th>Artículo</th><th class="num">Cant.</th><th class="num">Precio ud.</th><th class="num">Importe</th></tr></thead>
    <tbody>
    @foreach ($order->lines as $l)
        <tr>
            <td class="muted">{{ $l->article->reference }}</td>
            <td>{{ $l->article->name }}@if ($l->article->dimensions)<br><span class="muted" style="font-size:8pt">{{ $l->article->dimensions }}</span>@endif</td>
            <td class="num">{{ $l->quantity }}</td>
            <td class="num">{{ $eur($l->unit_price) }}</td>
            <td class="num">{{ $eur($l->total()) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<table class="totals">
    <tr><td>Material</td><td class="num">{{ $eur($order->subtotal()) }}</td></tr>
    @if ($order->discount_percent > 0)<tr><td>Descuento {{ $pct($order->discount_percent) }} %</td><td class="num">− {{ $eur($order->discountAmount()) }}</td></tr>@endif
    <tr><td>Transporte @if ($order->distance_km)<span class="muted">({{ $order->distance_km }} km, {{ $order->trucks }} {{ $order->trucks > 1 ? 'camiones' : 'camión' }})</span>@endif</td><td class="num">{{ $eur($order->transport_amount) }}</td></tr>
    <tr><td>Base imponible</td><td class="num">{{ $eur($order->taxableBase()) }}</td></tr>
    <tr><td>IVA {{ $pct($order->vat_rate) }} %</td><td class="num">{{ $eur($order->vatAmount()) }}</td></tr>
    <tr class="sub"><td><b>Subtotal</b> <span class="muted">(IVA incluido)</span></td><td class="num"><b>{{ $eur($order->total()) }}</b></td></tr>
    @if ($order->deposit_amount > 0)
        <tr><td>Fianza <span class="muted">(no sujeta a IVA; se devuelve tras revisar el material)</span></td><td class="num">{{ $eur($order->deposit_amount) }}</td></tr>
    @endif
    <tr class="grand"><td>TOTAL A PAGAR</td><td class="num">{{ $eur($order->amountDue()) }}</td></tr>
</table>

@if ($company['iban'])
<div class="pay">
    <div class="label">Forma de pago · Transferencia bancaria</div>
    IBAN <b>{{ $company['iban'] }}</b> · Titular {{ $company['name'] }}<br>
    Concepto: <b>{{ $order->number }}</b> · Importe: <b>{{ $eur($order->amountDue()) }}</b>
</div>
@endif

@if ($terms)<div class="terms">{!! nl2br(e($terms)) !!}</div>@endif

{{-- Página 2: precios de reposición del material alquilado --}}
<div style="page-break-before: always"></div>
<table class="head"><tr>
    <td style="width:80px"><img class="logo" src="{{ public_path('img/logo-320.png') }}" alt=""></td>
    <td><div class="serif" style="font-size:15pt">Viva la Virgen</div><div class="muted">Anexo a la proforma {{ $order->number }}</div></td>
    <td style="text-align:right"><h1 style="font-size:16pt">PRECIOS DE REPOSICIÓN</h1><div class="tag">Material alquilado</div></td>
</tr></table>
<p>Si alguna pieza se devuelve rota, deteriorada o no se devuelve, se facturará a su precio de reposición por unidad, descontándolo de la fianza. Estos son los precios del material de este pedido:</p>
<table class="lines">
    <thead><tr><th style="width:70px">Ref.</th><th>Artículo</th><th class="num">Cant.</th><th class="num">Reposición ud.</th><th class="num">Valor total</th></tr></thead>
    <tbody>
    @foreach ($order->lines as $l)
        <tr>
            <td class="muted">{{ $l->article->reference }}</td>
            <td>{{ $l->article->name }}@if ($l->article->dimensions)<br><span class="muted" style="font-size:8pt">{{ $l->article->dimensions }}</span>@endif</td>
            <td class="num">{{ $l->quantity }}</td>
            <td class="num">{{ $eur($l->article->replacement_price) }}</td>
            <td class="num">{{ $eur($l->quantity * $l->article->replacement_price) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
<table class="totals">
    <tr class="grand"><td>VALOR TOTAL DEL MATERIAL</td><td class="num">{{ $eur($order->lines->sum(fn ($l) => $l->quantity * $l->article->replacement_price)) }}</td></tr>
</table>
<p class="terms">Precios sin IVA. El valor total es informativo: solo se factura la reposición de las piezas dañadas o no devueltas, tras revisar el material a su regreso.</p>
</body>
</html>
