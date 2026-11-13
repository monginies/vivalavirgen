@extends('layouts.app')
@section('title', 'Pedido '.$order->number)
@section('content')
@php($money = fn ($v) => \App\Support\Money::eur($v))
<div class="head">
    <div>
        <h1>Pedido <span class="mono">{{ $order->number }}</span> <span class="badge {{ $order->status->color() }}">{{ $order->status->label() }}</span>@if ($order->source === 'web') <span class="badge blue">Web</span>@endif</h1>
        <div class="sub"><a href="{{ route('clients.show', $order->client) }}">{{ $order->client->name }}</a> · creado el {{ $order->created_at->format('d/m/Y') }}@if ($order->creator) por {{ $order->creator->name }}@endif</div>
    </div>
    @if ($order->status->isEditable())
        <a class="btn sec" href="{{ route('orders.edit', $order) }}">Editar</a>
    @endif
</div>

@if ($order->status->isExpirable() && $order->expires_at)
    <div class="flash {{ $order->expires_at->diffInHours(now(), true) < 24 ? 'ko' : 'ok' }}">
        Pre-reserva hasta el <b>{{ $order->expires_at->format('d/m/Y H:i') }}</b>. Si no se paga antes, caduca y el material se libera.
        <form method="post" action="{{ route('orders.extend', $order) }}" style="display:inline-flex;gap:6px;margin-left:8px">
            @csrf <input type="hidden" name="days" value="3"><button class="btn sec sm">Ampliar 3 días</button>
        </form>
    </div>
@endif
@if ($order->paid_at)
    <div class="flash ok">Marcado como pagado el <b>{{ $order->paid_at->format('d/m/Y') }}</b> a las <b>{{ $order->paid_at->format('H:i') }}</b>@if ($order->payer) por {{ $order->payer->name }}@endif.</div>
@endif
@if ($order->status === \App\Enums\OrderStatus::Expired)
    <div class="flash ko">La pre-reserva caducó y el material quedó libre. Puedes reactivarla si sigue habiendo disponibilidad.</div>
@endif

<div class="card">
    <div class="actions">
        @if ($next = $order->status->next())
            <form method="post" action="{{ route('orders.transition', $order) }}">@csrf
                <input type="hidden" name="status" value="{{ $next[0]->value }}"><button class="btn">{{ $next[1] }}</button>
            </form>
        @endif
        @if ($order->status === \App\Enums\OrderStatus::Expired)
            <form method="post" action="{{ route('orders.transition', $order) }}">@csrf
                <input type="hidden" name="status" value="requested"><button class="btn">Reactivar pre-reserva</button>
            </form>
        @endif
        @if ($order->status->canCancel())
            <form method="post" action="{{ route('orders.transition', $order) }}" onsubmit="return confirm('¿Cancelar este pedido? El material quedará libre.')">@csrf
                <input type="hidden" name="status" value="cancelled"><button class="btn danger">Cancelar pedido</button>
            </form>
        @endif
        @unless ($next || $order->status === \App\Enums\OrderStatus::Expired || $order->status->canCancel())
            <span class="muted">Pedido finalizado.</span>
        @endunless
    </div>
</div>

@if (! in_array($order->status, [\App\Enums\OrderStatus::Cancelled, \App\Enums\OrderStatus::Expired], true))
<div class="card">
    <div class="head" style="margin-bottom:10px"><h2 style="margin:0">Proforma</h2>
        <a class="btn sec sm" href="{{ route('orders.proforma', $order) }}" target="_blank">Ver PDF</a></div>
    @if ($order->proforma_sent_at)
        <p class="muted" style="margin-top:0">Enviada el {{ $order->proforma_sent_at->format('d/m/Y H:i') }} a {{ $order->proforma_sent_to }}.</p>
    @endif
    @if (in_array($order->status, [\App\Enums\OrderStatus::Requested, \App\Enums\OrderStatus::Accepted, \App\Enums\OrderStatus::ProformaSent, \App\Enums\OrderStatus::Paid], true))
        <form method="post" action="{{ route('orders.proforma.send', $order) }}" class="grid" style="grid-template-columns:minmax(0,320px) 1fr auto;gap:10px;align-items:end">
            @csrf
            <div class="field" style="margin:0"><label for="f-pf-email">Enviar a</label><input id="f-pf-email" type="email" name="email" value="{{ old('email', $order->client->email) }}" required></div>
            <div class="field" style="margin:0"><label for="f-pf-note">Mensaje (opcional)</label><input id="f-pf-note" type="text" name="note" value="{{ old('note') }}" placeholder="Ej.: Hemos incluido el montaje del viernes por la tarde."></div>
            <button class="btn">{{ $order->proforma_sent_at ? 'Reenviar proforma' : 'Enviar proforma' }}</button>
        </form>
        @error('email')<div class="err">{{ $message }}</div>@enderror
        @if (in_array($order->status, [\App\Enums\OrderStatus::Requested, \App\Enums\OrderStatus::Accepted], true))
            <p class="hint">Al enviarla, el pedido pasa a «Proforma enviada».</p>
        @endif
    @endif
</div>
@endif

@unless (in_array($order->status, [\App\Enums\OrderStatus::Cancelled, \App\Enums\OrderStatus::Expired], true))
@php($shippable = in_array($order->status, [\App\Enums\OrderStatus::Paid, \App\Enums\OrderStatus::Delivered, \App\Enums\OrderStatus::Returned, \App\Enums\OrderStatus::Closed], true))
<div class="card" id="expedicion">
    <div class="head" style="margin-bottom:10px"><h2 style="margin:0">Expedición</h2>
        @if ($shippable)<a class="btn sm" href="{{ route('orders.expedition', $order) }}" target="_blank">Hoja de expedición (PDF)</a>
        @else<span class="muted">La hoja de expedición se puede sacar cuando el pedido está pagado.</span>@endif</div>
    @if ($order->status === \App\Enums\OrderStatus::Closed)
        <dl class="props">
            <dt>Entrega</dt><dd>{{ $order->delivery_time ?: '—' }}</dd><dt>Recogida</dt><dd>{{ $order->pickup_time ?: '—' }}</dd>
            <dt>Contacto en el lugar</dt><dd>{{ collect([$order->site_contact_name, $order->site_contact_phone])->filter()->join(' · ') ?: '—' }}</dd>
            @if ($order->logistics_notes)<dt>Notas</dt><dd>{!! nl2br(e($order->logistics_notes)) !!}</dd>@endif
        </dl>
    @else
    <form method="post" action="{{ route('orders.logistics', $order) }}">
        @csrf @method('patch')
        <div class="grid g2">
            @include('partials.field', ['name' => 'delivery_time', 'label' => 'Hora de entrega / montaje', 'value' => $order->delivery_time, 'hint' => 'Ej.: 10:00 o 9:00–11:00'])
            @include('partials.field', ['name' => 'pickup_time', 'label' => 'Hora de recogida / desmontaje', 'value' => $order->pickup_time])
            @include('partials.field', ['name' => 'site_contact_name', 'label' => 'Contacto en el lugar', 'value' => $order->site_contact_name, 'hint' => 'Planner, encargado de la finca…'])
            @include('partials.field', ['name' => 'site_contact_phone', 'label' => 'Teléfono del contacto', 'value' => $order->site_contact_phone, 'type' => 'tel'])
        </div>
        @include('partials.field', ['name' => 'logistics_notes', 'label' => 'Notas para logística', 'value' => $order->logistics_notes, 'type' => 'textarea',
            'hint' => 'Accesos, aparcamiento, planta, horarios de la finca, montaje especial… Sale en la hoja de expedición (las notas del pedido no).'])
        <button class="btn sec">Guardar datos de logística</button>
    </form>
    @endif
</div>
@endunless

<div class="grid g2">
    <div class="card">
        <h2>Fechas y destino</h2>
        <dl class="props">
            <dt>Evento</dt><dd>{{ $order->event_date?->format('d/m/Y') ?? '—' }}</dd>
            <dt>Entrega</dt><dd>{{ $order->start_date->format('d/m/Y') }}</dd>
            <dt>Recogida</dt><dd>{{ $order->end_date->format('d/m/Y') }}</dd>
            @if ($order->venue)<dt>Finca</dt><dd><a href="{{ route('venues.edit', $order->venue) }}">{{ $order->venue->name }}</a></dd>@endif
            <dt>Destino</dt><dd>{{ collect([$order->destination_address, trim($order->destination_postal_code.' '.$order->destination_city)])->filter()->join(', ') ?: '—' }}</dd>
            <dt>Distancia</dt><dd>{{ $order->distance_km ? number_format($order->distance_km, 0, ',', '.').' km (ida)' : '—' }}</dd>
            <dt>Camiones</dt><dd>{{ $order->trucks }}</dd>
        </dl>
    </div>
    <div class="card">
        <h2>Importes</h2>
        <dl class="props">
            <dt>Material</dt><dd>{{ $money($order->subtotal()) }}</dd>
            @if ($order->discount_percent > 0)
                <dt>Descuento {{ rtrim(rtrim(number_format($order->discount_percent, 2, ',', ''), '0'), ',') }} %</dt><dd>− {{ $money($order->discountAmount()) }}</dd>
            @endif
            <dt>Transporte</dt><dd>{{ $money($order->transport_amount) }}@if ($order->transport_manual) <span class="muted">(a mano)</span>@endif</dd>
            <dt>Base imponible</dt><dd>{{ $money($order->taxableBase()) }}</dd>
            <dt>IVA {{ rtrim(rtrim(number_format($order->vat_rate, 2, ',', ''), '0'), ',') }} %</dt><dd>{{ $money($order->vatAmount()) }}</dd>
            @if ($order->withholding_rate > 0)
                <dt>Retención IRPF {{ rtrim(rtrim(number_format($order->withholding_rate, 2, ',', ''), '0'), ',') }} %</dt><dd>− {{ $money($order->withholdingAmount()) }}</dd>
            @endif
            <dt>Total</dt><dd>{{ $money($order->total()) }} <span class="muted">IVA incl.@if ($order->withholding_rate > 0), retención desc.@endif</span></dd>
            <dt>Fianza</dt><dd>{{ $money($order->deposit_amount) }} <span class="muted">(se devuelve)</span></dd>
            <dt><b>Total a pagar</b></dt><dd><b>{{ $money($order->amountDue()) }}</b></dd>
        </dl>
    </div>
</div>

<div class="card table-wrap">
    <h2>Material</h2>
    <table>
        <thead><tr><th>Artículo</th><th class="num">Cantidad</th><th class="num">Precio ud.</th><th class="num">Importe</th><th class="num">Libres en esas fechas</th></tr></thead>
        <tbody>
        @foreach ($order->lines as $l)
            @php($av = $availability[$l->article_id])
            <tr>
                <td><span class="mono">{{ $l->article->reference }}</span> · <a href="{{ route('articles.show', $l->article) }}">{{ $l->article->name }}</a>
                    @if ($inc = $l->article->componentsSummary())<br><span class="muted" style="font-size:13px">Incluye: {{ $inc }}</span>@endif</td>
                <td class="num">{{ $l->quantity }}</td>
                <td class="num">{{ $money($l->unit_price) }}</td>
                <td class="num">{{ $money($l->total()) }}</td>
                <td class="num">
                    @if ($order->status->isBlocking() && $l->quantity > $av['available'])
                        <span class="badge red">Faltan {{ $l->quantity - max(0, $av['available']) }}</span>
                    @else
                        <span class="muted">{{ $av['available'] - ($order->status->isBlocking() ? $l->quantity : 0) }} de {{ $av['capacity'] }}</span>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

@if ($order->notes)
    <div class="card"><h2>Notas</h2><p>{!! nl2br(e($order->notes)) !!}</p></div>
@endif
@endsection
