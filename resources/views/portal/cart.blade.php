@extends('layouts.portal')
@section('title', 'Mi pedido')
@section('content')
@php
    $money = fn ($v) => \App\Support\Money::eur($v);
    $approved = $client?->isApproved();
    $short = $lines->filter(fn ($l) => isset($availability[$l['article']->id]) && $l['quantity'] > $availability[$l['article']->id]);
@endphp
<div class="head"><h1>Mi pedido</h1>@if ($lines->isNotEmpty())<a class="btn sec" href="{{ route('portal.home') }}">+ Seguir añadiendo</a>@endif</div>

@if ($lines->isEmpty())
    <div class="card empty">Tu pedido está vacío. <a href="{{ route('portal.home') }}">Ver el catálogo</a></div>
@else
@foreach ($errors->all() as $e)<div class="flash ko">{{ $e }}</div>@endforeach
<form method="post" action="{{ route('portal.cart.update') }}" id="cart-form" class="pt-cartgrid">
    @csrf
    <div>
        <div class="card">
            <h2>1. Fecha del evento</h2>
            <div class="grid" style="grid-template-columns:minmax(0,220px) 1fr;gap:14px;align-items:end">
                <div class="field" style="margin:0">
                    <label for="f-event_date">Fecha</label>
                    <input id="f-event_date" type="date" name="event_date" min="{{ today()->toDateString() }}" value="{{ old('event_date', $cart['event_date']) }}">
                </div>
                <div class="hint" id="period-hint" style="margin:0 0 10px">
                    @if ($period)
                        Reservamos el material del <b>{{ \Carbon\Carbon::parse($period[0])->isoFormat('dddd D [de] MMMM') }}</b> al <b>{{ \Carbon\Carbon::parse($period[1])->isoFormat('dddd D [de] MMMM') }}</b>.
                    @else
                        Si el evento es en fin de semana, reservamos el material de viernes a domingo.
                    @endif
                </div>
            </div>
        </div>

        <div class="card table-wrap">
            <h2>2. Material</h2>
            <table class="pt-lines">
                <thead><tr><th colspan="2">Artículo</th><th class="num" style="width:110px">Cantidad</th>@if ($approved)<th class="num">Importe</th>@endif<th></th></tr></thead>
                <tbody>
                @foreach ($lines as $l)
                    @php($a = $l['article'])
                    @php($free = $availability[$a->id] ?? null)
                    <tr data-price="{{ $approved ? $a->rental_price : 0 }}">
                        <td style="width:64px">@if ($a->mainPhoto)<img class="thumb" src="{{ $a->mainPhoto->thumbUrl() }}" alt="">@else<div class="thumb"></div>@endif</td>
                        <td>
                            <a href="{{ route('portal.article', $a->reference) }}">{{ $a->name }}</a>
                            @if ($free !== null)
                                <div class="hint">@if ($l['quantity'] > $free)<span class="err" style="margin:0">Solo quedan {{ $free }} libres para tu fecha</span>@else{{ $free }} libres para tu fecha @endif</div>
                            @endif
                        </td>
                        <td class="num"><input type="number" name="quantities[{{ $a->id }}]" value="{{ $l['quantity'] }}" min="0" max="10000" class="q" style="max-width:96px;text-align:right"></td>
                        @if ($approved)<td class="num l-total"></td>@endif
                        <td class="num"><button class="btn danger sm" form="rm-{{ $a->id }}" title="Quitar">×</button></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div class="actions" style="margin-top:10px"><button class="btn sec sm">Actualizar cantidades</button></div>
        </div>

        <div class="card">
            <h2>3. Lugar del evento</h2>
            @if ($venues->isNotEmpty())
                <div class="field">
                    <label for="f-venue_id">Finca o lugar</label>
                    <select id="f-venue_id" name="venue_id">
                        <option value="">— Otro lugar (escribe la dirección) —</option>
                        @foreach ($venues as $v)
                            <option value="{{ $v->id }}" @selected(old('venue_id', $cart['venue_id']) == $v->id)>{{ $v->name }}{{ $v->city ? ' · '.$v->city : '' }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div id="own-address">
                @include('partials.field', ['name' => 'address', 'label' => 'Dirección', 'value' => $cart['address']])
                <div class="grid" style="grid-template-columns:130px 1fr;gap:10px">
                    @include('partials.field', ['name' => 'postal_code', 'label' => 'C. postal', 'value' => $cart['postal_code'], 'attrs' => 'inputmode=numeric maxlength=5 autocomplete=postal-code data-cp-city=f-city'])
                    @include('partials.field', ['name' => 'city', 'label' => 'Población', 'value' => $cart['city']])
                </div>
            </div>
            <div class="hint" id="transport-hint"></div>
        </div>

        <div class="card">
            <h2>4. Comentarios</h2>
            @include('partials.field', ['name' => 'notes', 'label' => 'Algo que debamos saber (horarios, accesos…)', 'value' => $cart['notes'], 'type' => 'textarea'])
        </div>
    </div>

    <aside class="card pt-summary">
        <h2>Resumen</h2>
        @if ($approved)
            <dl class="props">
                <dt>Material</dt><dd id="s-sub">—</dd>
                @if ($discount > 0)<dt>Tu descuento {{ rtrim(rtrim(number_format($discount, 2, ',', ''), '0'), ',') }} %</dt><dd id="s-disc">—</dd>@endif
                <dt>Transporte</dt><dd id="s-tr">Indica el lugar</dd>
                <dt>IVA {{ rtrim(rtrim(number_format($settings['vat'], 2, ',', ''), '0'), ',') }} %</dt><dd id="s-vat">—</dd>
                @if ($settings['withholding'] > 0)<dt>Retención IRPF {{ rtrim(rtrim(number_format($settings['withholding'], 2, ',', ''), '0'), ',') }} %</dt><dd id="s-wh">—</dd>@endif
                <dt>Total</dt><dd id="s-total">—</dd>
                <dt>Fianza</dt><dd>{{ $money($settings['deposit']) }}<div class="hint" style="margin:0">Se devuelve tras revisar el material</div></dd>
                <dt><b>Total</b></dt><dd><b id="s-due">—</b></dd>
            </dl>
            <p class="hint">Importe estimado. Te confirmaremos el precio final en la proforma.</p>
            @if ($short->isNotEmpty())<p class="err">Algún artículo no tiene unidades suficientes para tu fecha: ajusta las cantidades y pulsa «Actualizar cantidades».</p>@endif
            @if (! $period)<p class="hint">Indica la fecha del evento para poder enviarlo.</p>@endif
            <button class="btn" style="width:100%;justify-content:center" formaction="{{ route('portal.checkout') }}" @disabled($short->isNotEmpty() || ! $period)>Solicitar pedido</button>
            <p class="hint">Al solicitarlo, el material queda pre-reservado {{ (int) \App\Models\Setting::number('prereservation_days') }} días mientras preparamos la proforma.</p>
        @elseif ($client)
            <p>Tu cuenta está pendiente de aprobación. Puedes dejar el pedido preparado; cuando la activemos verás los precios y podrás enviarlo.</p>
            <button class="btn sec" style="width:100%;justify-content:center">Guardar pedido</button>
        @else
            <p>Para ver precios y enviar el pedido, entra con tu cuenta o crea una. Lo que has elegido se mantiene.</p>
            <a class="btn" style="width:100%;justify-content:center" href="{{ route('portal.login') }}">Entrar</a>
            <a class="btn sec" style="width:100%;justify-content:center;margin-top:8px" href="{{ route('portal.register') }}">Crear cuenta</a>
        @endif
    </aside>
</form>
@foreach ($lines as $l)
    <form id="rm-{{ $l['article']->id }}" method="post" action="{{ route('portal.cart.remove', $l['article']) }}">@csrf @method('delete')</form>
@endforeach
@endif
@push('scripts')
<script>
(() => {
    const form = document.getElementById('cart-form');
    if (!form) return;
    const approved = @json((bool) $approved), discount = @json((float) $discount), vat = @json((float) $settings['vat']), wh = @json((float) $settings['withholding']), deposit = @json((float) $settings['deposit']);
    const eur = n => n.toLocaleString('es-ES', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' €';
    let transport = null;

    function recalc() {
        if (!approved) return;
        let sub = 0;
        form.querySelectorAll('tbody tr').forEach(tr => {
            const t = (parseFloat(tr.dataset.price) || 0) * (parseInt(tr.querySelector('.q').value) || 0);
            tr.querySelector('.l-total').textContent = eur(t);
            sub += t;
        });
        const disc = sub * discount / 100, tr = transport || 0, base = sub - disc + tr;
        document.getElementById('s-sub').textContent = eur(sub);
        if (document.getElementById('s-disc')) document.getElementById('s-disc').textContent = '− ' + eur(disc);
        document.getElementById('s-tr').textContent = transport === null ? 'Indica el lugar' : eur(tr);
        document.getElementById('s-vat').textContent = eur(base * vat / 100);
        if (document.getElementById('s-wh')) document.getElementById('s-wh').textContent = '− ' + eur(base * wh / 100);
        document.getElementById('s-total').textContent = eur(base * (1 + vat / 100 - wh / 100));
        document.getElementById('s-due').textContent = eur(base * (1 + vat / 100 - wh / 100) + deposit);
    }

    // Transporte estimado según finca o dirección
    const venue = document.getElementById('f-venue_id'), own = document.getElementById('own-address'), hint = document.getElementById('transport-hint');
    let timer;
    async function estimate() {
        const params = new URLSearchParams({
            venue_id: venue ? venue.value : '',
            address: document.getElementById('f-address').value,
            postal_code: document.getElementById('f-postal_code').value,
            city: document.getElementById('f-city').value,
        });
        if (!params.get('venue_id') && !params.get('postal_code') && !params.get('city')) { transport = null; hint.textContent = ''; recalc(); return; }
        hint.className = 'hint'; hint.textContent = 'Calculando transporte…';
        try {
            const res = await fetch(@json(route('portal.cart.transport')) + '?' + params, {headers: {Accept: 'application/json'}});
            const data = await res.json();
            if (!res.ok) throw new Error(data.error);
            transport = data.amount;
            hint.textContent = approved ? 'Transporte estimado: ' + data.km + ' km desde nuestro almacén.' : '';
        } catch (e) {
            transport = null; hint.className = 'hint';
            hint.textContent = (e instanceof TypeError || !e.message ? 'No hemos podido calcular el transporte ahora.' : e.message) + ' Lo calcularemos nosotros al revisar el pedido.';
        }
        recalc();
    }
    const toggleOwn = () => { if (venue) own.style.display = venue.value ? 'none' : ''; };
    if (venue) venue.addEventListener('change', () => { toggleOwn(); estimate(); });
    ['f-address', 'f-postal_code', 'f-city'].forEach(id => document.getElementById(id).addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(estimate, 700); }));
    toggleOwn(); estimate();

    form.addEventListener('input', e => { if (e.target.classList.contains('q')) recalc(); });
    // Al cambiar la fecha se guarda para ver la disponibilidad de ese fin de semana
    document.getElementById('f-event_date').addEventListener('change', () => form.requestSubmit());
    recalc();
})();
</script>
@endpush
@endsection
