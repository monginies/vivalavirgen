@extends('layouts.app')
@section('title', 'Pedido '.$order->number)
@section('content')
@php($money = fn ($v) => \App\Support\Money::eur($v))
<div class="head">
    <div>
        <h1>Pedido <span class="mono">{{ $order->number }}</span> <span class="badge {{ $order->status->color() }}">{{ $order->status->label() }}</span></h1>
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

<div class="grid g2">
    <div class="card">
        <h2>Fechas y destino</h2>
        <dl class="props">
            <dt>Evento</dt><dd>{{ $order->event_date?->format('d/m/Y') ?? '—' }}</dd>
            <dt>Entrega</dt><dd>{{ $order->start_date->format('d/m/Y') }}</dd>
            <dt>Recogida</dt><dd>{{ $order->end_date->format('d/m/Y') }}</dd>
            <dt>Destino</dt><dd>{{ $order->destination() ?: '—' }}</dd>
            <dt>Distancia</dt><dd>{{ $order->distance_km ? number_format($order->distance_km, 1, ',', '.').' km (ida)' : '—' }}</dd>
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
            <dt><b>Total</b></dt><dd><b>{{ $money($order->total()) }}</b></dd>
            <dt>Fianza</dt><dd>{{ $money($order->deposit_amount) }} <span class="muted">(aparte, se devuelve)</span></dd>
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
                <td><span class="mono">{{ $l->article->reference }}</span> · <a href="{{ route('articles.show', $l->article) }}">{{ $l->article->name }}</a></td>
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
