<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Expedición {{ $order->number }}</title>
<style>
    @page { margin: 18mm 14mm 18mm 14mm; }
    * { font-family: "DejaVu Sans", sans-serif; }
    body { font-size: 9pt; color: #222222; line-height: 1.4; }
    .serif { font-family: "DejaVu Serif", serif; }
    .muted { color: #666666; }
    .head { width: 100%; border-bottom: 2px solid #888888; padding-bottom: 8px; margin-bottom: 12px; }
    .head td { vertical-align: top; }
    .logo { width: 56px; height: 56px; }
    h1 { font-family: "DejaVu Serif", serif; font-weight: normal; font-size: 18pt; margin: 0; letter-spacing: 1px; }
    .label { font-size: 6.8pt; letter-spacing: 1.2px; text-transform: uppercase; color: #666666; margin-bottom: 2px; }
    .grid { width: 100%; border-collapse: separate; border-spacing: 6px; margin: 0 -6px 6px; }
    .grid td { vertical-align: top; padding: 8px 10px; background: #f0f0f0; }
    .big { font-size: 11pt; font-weight: bold; }
    .notes { border: 1px solid #bbbbbb; padding: 8px 10px; margin-bottom: 12px; }
    table.lines { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    table.lines th { font-size: 6.8pt; letter-spacing: 1px; text-transform: uppercase; color: #666666; text-align: left; border-bottom: 1px solid #888888; padding: 5px 4px; font-weight: normal; }
    table.lines td { padding: 4px; border-bottom: 1px solid #dddddd; vertical-align: middle; }
    td.ref { white-space: nowrap; }
    table.lines th.c, table.lines td.c { text-align: center; width: 46px; }
    .num { text-align: right; white-space: nowrap; }
    .thumb { width: 38px; height: 38px; }
    .noimg { width: 38px; height: 38px; background: #eeeeee; }
    .box { display: inline-block; width: 11px; height: 11px; border: 1.2px solid #222222; }
    tr.bundle td { background: #f0f0f0; font-weight: bold; }
    tr.part td.name { padding-left: 16px; }
    .sign { width: 100%; border-collapse: separate; border-spacing: 8px; margin: 6px -8px 0; page-break-inside: avoid; }
    .sign td { width: 50%; vertical-align: top; border: 1px solid #888888; padding: 8px 10px; }
    .field.tall { height: 40px; }
    .field { border-bottom: 1px solid #bbbbbb; height: 18px; margin-bottom: 6px; font-size: 7.5pt; color: #666666; }
    .foot { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 7pt; color: #888888; text-align: center; }
</style>
</head>
<body>
<div class="foot">Viva la Virgen · Hoja de expedición {{ $order->number }} · Documento interno de logística, sin precios</div>

<table class="head"><tr>
    <td style="width:66px">@if ($logo = \App\Support\Expedition::gray(public_path('img/logo-320.png'), 160))<img class="logo" src="{{ $logo }}" alt="">@endif</td>
    <td><div class="serif" style="font-size:13pt">Viva la Virgen</div><div class="muted">Logística · {{ now()->format('d/m/Y H:i') }}</div></td>
    <td style="text-align:right">
        <h1>HOJA DE EXPEDICIÓN</h1>
        <div style="margin-top:4px"><b style="font-size:11pt">{{ $order->number }}</b> · {{ $order->status->label() }}
            @if ($order->paid_at)<br><span class="muted">Pagado el {{ $order->paid_at->format('d/m/Y H:i') }}</span>@endif</div>
    </td>
</tr></table>

<table class="grid"><tr>
    <td style="width:34%">
        <div class="label">Entrega / montaje</div>
        <div class="big">{{ $order->start_date->isoFormat('ddd D/MM/YYYY') }}</div>
        {{ $order->delivery_time ?: 'Hora por confirmar' }}
    </td>
    <td style="width:33%">
        <div class="label">Evento</div>
        <div class="big">{{ $order->event_date ? $order->event_date->isoFormat('ddd D/MM/YYYY') : '—' }}</div>
    </td>
    <td style="width:33%">
        <div class="label">Recogida / desmontaje</div>
        <div class="big">{{ $order->end_date->isoFormat('ddd D/MM/YYYY') }}</div>
        {{ $order->pickup_time ?: 'Hora por confirmar' }}
    </td>
</tr></table>

<table class="grid"><tr>
    <td style="width:50%">
        <div class="label">Lugar</div>
        @if ($order->venue)<b>{{ $order->venue->name }}</b><br>@endif
        {{ collect([$order->destination_address, trim($order->destination_postal_code.' '.$order->destination_city)])->filter()->join(', ') ?: 'Por confirmar' }}<br>
        <span class="muted">{{ $order->distance_km ? $order->distance_km.' km desde la base · ' : '' }}{{ $order->trucks }} {{ $order->trucks > 1 ? 'camiones' : 'camión' }}</span>
    </td>
    <td style="width:25%">
        <div class="label">Contacto en el lugar</div>
        @if ($order->site_contact_name || $order->site_contact_phone)
            <b>{{ $order->site_contact_name }}</b><br>{{ $order->site_contact_phone }}
        @else<span class="muted">—</span>@endif
    </td>
    <td style="width:25%">
        <div class="label">Cliente</div>
        @php($c = $order->client)
        <b>{{ $c->name }}</b>@if ($c->trade_name)<br><span class="muted">{{ $c->trade_name }}</span>@endif<br>
        {{ collect([$c->contact_name, $c->phone])->filter()->join(' · ') }}
    </td>
</tr></table>

@if ($order->logistics_notes)
    <div class="notes"><div class="label">Notas para logística</div>{!! nl2br(e($order->logistics_notes)) !!}</div>
@endif

<table class="lines">
    <thead><tr>
        <th style="width:44px"></th><th style="width:72px">Ref.</th><th>Artículo</th><th class="num" style="width:40px">Cant.</th>
        <th class="c">Carga</th><th class="c">Entrega</th><th class="c">Vuelta</th>
    </tr></thead>
    <tbody>
    @php($pieces = 0)
    @foreach ($rows as $r)
        @php($a = $r['article'])
        @if ($r['bundle'])
            <tr class="bundle">
                <td>@if ($img = \App\Support\Expedition::thumb($a))<img class="thumb" src="{{ $img }}" alt="">@endif</td>
                <td class="ref">{{ $a->reference }}</td>
                <td class="name">{{ $a->name }} <span class="muted" style="font-weight:normal">(combinado: se carga por piezas)</span></td>
                <td class="num">{{ $r['quantity'] }}</td><td></td><td></td><td></td>
            </tr>
        @else
            @php($pieces += $r['quantity'])
            <tr @class(['part' => $r['part']])>
                <td>@if ($img = \App\Support\Expedition::thumb($a))<img class="thumb" src="{{ $img }}" alt="">@else<div class="noimg"></div>@endif</td>
                <td class="muted ref">{{ $a->reference }}</td>
                <td class="name">@if ($r['part'])↳ @endif{{ $a->name }}@if ($a->dimensions)<br><span class="muted" style="font-size:7.5pt">{{ $a->dimensions }}</span>@endif</td>
                <td class="num"><b>{{ $r['quantity'] }}</b></td>
                <td class="c"><span class="box"></span></td><td class="c"><span class="box"></span></td><td class="c"><span class="box"></span></td>
            </tr>
        @endif
    @endforeach
    </tbody>
</table>
<p class="muted" style="margin:0 0 10px">Total de piezas a cargar: <b>{{ $pieces }}</b></p>

<table class="sign"><tr>
    <td>
        <div class="label">Entrega · conformidad del cliente</div>
        <div class="field">Fecha y hora</div>
        <div class="field">Nombre</div>
        <div class="field tall">Firma</div>
        <div class="field">Observaciones</div>
    </td>
    <td>
        <div class="label">Recogida · conformidad del cliente</div>
        <div class="field">Fecha y hora</div>
        <div class="field">Nombre</div>
        <div class="field tall">Firma</div>
        <div class="field">Incidencias (roturas, faltas, suciedad)</div>
    </td>
</tr></table>
</body>
</html>
