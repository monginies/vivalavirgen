@extends('layouts.app')
@section('title', $order->exists ? 'Editar pedido' : 'Nuevo pedido')
@section('content')
@php
    $oldLines = old('lines', $order->lines->map(fn ($l) => ['article_id' => $l->article_id, 'quantity' => $l->quantity, 'unit_price' => $l->unit_price])->all());
    $oldLines = array_values($oldLines ?: [['article_id' => null, 'quantity' => 1, 'unit_price' => null]]);
@endphp
<div class="head">
    <div>
        <h1>{{ $order->exists ? 'Editar pedido '.$order->number : 'Nuevo pedido' }}</h1>
        @unless ($order->exists)<div class="sub">Al guardar, el material queda pre-reservado para esas fechas.</div>@endunless
    </div>
</div>
@if ($clients->isEmpty())
    <div class="flash ko">Antes de crear un pedido hay que dar de alta al cliente. <a href="{{ route('clients.create') }}">Nuevo cliente</a></div>
@endif
<form method="post" action="{{ $order->exists ? route('orders.update', $order) : route('orders.store') }}" id="order-form">
    @csrf @if ($order->exists) @method('put') @endif
    <div class="card">
        <h2>Cliente y fechas</h2>
        <div class="grid g2">
            <div class="field">
                <label for="f-client_id">Cliente *</label>
                <select id="f-client_id" name="client_id" required>
                    <option value="">Elige un cliente…</option>
                    @foreach ($clients as $c)
                        <option value="{{ $c->id }}" data-discount="{{ $c->category?->discount_percent ?? 0 }}" @selected(old('client_id', $order->client_id) == $c->id)>{{ $c->name }}{{ $c->trade_name ? ' ('.$c->trade_name.')' : '' }}</option>
                    @endforeach
                </select>
                @error('client_id')<div class="err">{{ $message }}</div>@enderror
            </div>
            @include('partials.field', ['name' => 'event_date', 'label' => 'Fecha del evento', 'value' => $order->event_date?->toDateString(), 'type' => 'date',
                'hint' => 'Si es viernes, sábado o domingo, el material se reserva de viernes a domingo.'])
            @include('partials.field', ['name' => 'start_date', 'label' => 'Entrega (desde)', 'value' => $order->start_date?->toDateString(), 'type' => 'date',
                'hint' => 'Desde este día el material queda reservado.'])
            @include('partials.field', ['name' => 'end_date', 'label' => 'Recogida (hasta)', 'value' => $order->end_date?->toDateString(), 'type' => 'date',
                'hint' => 'Último día reservado. Si el material vuelve tarde, sigue bloqueado hasta que se da entrada.'])
        </div>
    </div>

    <div class="card">
        <h2>Material</h2>
        @foreach (array_merge($errors->get('lines'), collect($errors->get('lines.*'))->flatten()->all()) as $m)
            <div class="flash ko">{{ $m }}</div>
        @endforeach
        <div class="table-wrap">
        <table id="lines">
            <thead><tr><th>Artículo</th><th class="num" style="width:110px">Cantidad</th><th class="num" style="width:140px">Precio ud. (sin IVA)</th><th class="num" style="width:120px">Importe</th><th style="width:40px"></th></tr></thead>
            <tbody>
            @foreach ($oldLines as $i => $line)
                <tr class="line">
                    <td>
                        <select name="lines[{{ $i }}][article_id]" class="l-article">
                            <option value="">—</option>
                            @foreach ($articles->groupBy(fn ($a) => $a->category->name) as $cat => $group)
                                <optgroup label="{{ $cat }}">
                                @foreach ($group as $a)
                                    <option value="{{ $a->id }}" data-price="{{ $a->rental_price }}" @selected($line['article_id'] == $a->id)>{{ $a->reference }} · {{ $a->name }}</option>
                                @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </td>
                    <td><input type="number" name="lines[{{ $i }}][quantity]" class="l-qty" min="1" step="1" value="{{ $line['quantity'] }}"></td>
                    <td><input type="number" name="lines[{{ $i }}][unit_price]" class="l-price" min="0" step="0.01" value="{{ $line['unit_price'] }}"></td>
                    <td class="num l-total">—</td>
                    <td><button type="button" class="btn sec sm l-del" title="Quitar">×</button></td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        <div class="actions" style="margin-top:12px">
            <button type="button" class="btn sec sm" id="add-line">+ Añadir artículo</button>
            <a class="btn sec sm" href="{{ route('availability') }}" target="_blank">Ver disponibilidad</a>
        </div>
    </div>

    <div class="card">
        <h2>Destino y transporte</h2>
        <div class="field">
            <label for="f-venue_id">Finca / lugar habitual</label>
            <select id="f-venue_id" name="venue_id">
                <option value="">— Otra dirección (escribirla abajo) —</option>
                @foreach ($venues as $v)
                    <option value="{{ $v->id }}" data-address="{{ $v->address }}" data-city="{{ $v->city }}" data-cp="{{ $v->postal_code }}" data-km="{{ $v->distance_km }}"
                        @selected(old('venue_id', $order->venue_id) == $v->id)>{{ $v->name }}{{ $v->place() ? ' · '.$v->place() : '' }}</option>
                @endforeach
            </select>
            <div class="hint">Al elegir una finca se rellenan la dirección y los km. <a href="{{ route('venues.index') }}" target="_blank">Gestionar fincas</a></div>
            @error('venue_id')<div class="err">{{ $message }}</div>@enderror
        </div>
        <div class="grid g2">
            @include('partials.field', ['name' => 'destination_address', 'label' => 'Dirección / finca', 'value' => $order->destination_address])
            <div class="grid" style="grid-template-columns:120px 1fr;gap:10px">
                @include('partials.field', ['name' => 'destination_postal_code', 'label' => 'C. postal', 'value' => $order->destination_postal_code, 'attrs' => 'inputmode=numeric maxlength=5 pattern=[0-9]{5} autocomplete=off'])
                <div class="field">
                    <label for="f-destination_city">Población</label>
                    <input id="f-destination_city" type="text" name="destination_city" list="cp-places" value="{{ old('destination_city', $order->destination_city) }}">
                    <datalist id="cp-places"></datalist>
                    <div class="hint" id="cp-hint">Con el código postal se sitúa el destino en cualquier provincia.</div>
                    @error('destination_city')<div class="err">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="field">
                <label for="f-distance_km">Distancia desde la base (km, solo ida)</label>
                <div style="display:flex;gap:8px">
                    <input id="f-distance_km" type="number" name="distance_km" step="1" min="0" value="{{ old('distance_km', $order->distance_km) }}">
                    @if ($mapsEnabled)
                        <button type="button" class="btn sec" id="calc-km" style="white-space:nowrap">Calcular km</button>
                    @endif
                </div>
                <div class="hint" id="km-hint">{{ $mapsEnabled ? 'Por carretera, con OpenRouteService. Puedes corregirlo a mano.' : 'Escríbelo a mano (el cálculo automático no está activado).' }}</div>
                @error('distance_km')<div class="err">{{ $message }}</div>@enderror
            </div>
            @include('partials.field', ['name' => 'trucks', 'label' => 'Camiones', 'value' => $order->trucks ?? 1, 'type' => 'number', 'step' => 1, 'min' => 1, 'required' => true])
        </div>
        <div class="field">
            <label class="check"><input type="checkbox" name="transport_manual" value="1" id="f-transport_manual" @checked(old('transport_manual', $order->transport_manual))> Fijar el importe del transporte a mano</label>
        </div>
        <div class="grid g2">
            @include('partials.field', ['name' => 'transport_amount', 'label' => 'Importe del transporte (sin IVA)', 'value' => $order->transport_amount, 'type' => 'number', 'step' => '0.01', 'min' => 0,
                'hint' => '2 trayectos × km × '.number_format($settings['per_km'], 2, ',', '.').' €/km × camiones, mínimo '.number_format($settings['minimum'], 2, ',', '.').' €.'])
        </div>
    </div>

    <div class="card">
        <h2>Condiciones</h2>
        <div class="grid g2">
            @include('partials.field', ['name' => 'discount_percent', 'label' => 'Descuento sobre el material (%)', 'value' => $order->discount_percent, 'type' => 'number', 'step' => '0.01', 'min' => 0])
            @include('partials.field', ['name' => 'deposit_amount', 'label' => 'Fianza (€)', 'value' => $order->deposit_amount, 'type' => 'number', 'step' => '0.01', 'min' => 0])
        </div>
        @include('partials.field', ['name' => 'notes', 'label' => 'Notas', 'value' => $order->notes, 'type' => 'textarea'])
        <dl class="props" id="summary">
            <dt>Material</dt><dd id="s-sub">—</dd>
            <dt>Transporte</dt><dd id="s-tr">—</dd>
            <dt>Base imponible aprox.</dt><dd id="s-base">—</dd>
        </dl>
    </div>

    <div class="actions">
        <button class="btn">{{ $order->exists ? 'Guardar cambios' : 'Crear pedido y pre-reservar' }}</button>
        <a class="btn sec" href="{{ $order->exists ? route('orders.show', $order) : route('orders.index') }}">Cancelar</a>
    </div>
</form>

<script>
(() => {
    const perKm = {{ $settings['per_km'] }}, minimum = {{ $settings['minimum'] }};
    const tbody = document.querySelector('#lines tbody');
    const eur = n => n.toLocaleString('es-ES', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' €';
    const num = el => parseFloat(el?.value) || 0;
    let next = tbody.rows.length;

    function recalc() {
        let sub = 0;
        tbody.querySelectorAll('tr.line').forEach(tr => {
            const t = num(tr.querySelector('.l-qty')) * num(tr.querySelector('.l-price'));
            tr.querySelector('.l-total').textContent = t ? eur(t) : '—';
            sub += t;
        });
        const manual = document.getElementById('f-transport_manual').checked;
        const trInput = document.getElementById('f-transport_amount');
        trInput.readOnly = !manual;
        if (!manual) {
            const km = num(document.getElementById('f-distance_km'));
            const trucks = Math.max(1, num(document.getElementById('f-trucks')));
            trInput.value = km ? Math.max(2 * km * perKm * trucks, minimum).toFixed(2) : '0.00';
        }
        const disc = sub * num(document.getElementById('f-discount_percent')) / 100;
        document.getElementById('s-sub').textContent = eur(sub - disc) + (disc ? ' (con ' + eur(disc) + ' de descuento)' : '');
        document.getElementById('s-tr').textContent = eur(num(trInput));
        document.getElementById('s-base').textContent = eur(sub - disc + num(trInput)) + ' + IVA';
    }

    tbody.addEventListener('change', e => {
        if (e.target.classList.contains('l-article')) {
            const price = e.target.selectedOptions[0]?.dataset.price;
            const p = e.target.closest('tr').querySelector('.l-price');
            if (price !== undefined) p.value = price;
        }
        recalc();
    });
    tbody.addEventListener('click', e => {
        if (!e.target.classList.contains('l-del')) return;
        if (tbody.rows.length > 1) e.target.closest('tr').remove();
        else e.target.closest('tr').querySelectorAll('select,input').forEach(el => el.value = '');
        recalc();
    });
    document.getElementById('add-line').addEventListener('click', () => {
        const tr = tbody.rows[0].cloneNode(true);
        tr.querySelectorAll('select,input').forEach(el => {
            el.name = el.name.replace(/lines\[\d+\]/, 'lines[' + next + ']');
            el.value = el.classList.contains('l-qty') ? 1 : '';
        });
        next++;
        tbody.appendChild(tr);
        recalc();
    });
    document.getElementById('order-form').addEventListener('input', recalc);

    document.getElementById('f-venue_id').addEventListener('change', e => {
        const o = e.target.selectedOptions[0];
        if (!o || !o.value) return;
        document.getElementById('f-destination_address').value = o.dataset.address || '';
        document.getElementById('f-destination_city').value = o.dataset.city || '';
        document.getElementById('f-destination_postal_code').value = o.dataset.cp || '';
        document.getElementById('f-distance_km').value = o.dataset.km || '';
        const hint = document.getElementById('km-hint');
        hint.className = 'hint';
        hint.textContent = o.dataset.km ? 'Km guardados en la ficha de la finca.' : 'Esta finca no tiene km guardados: pulsa «Calcular km» o escríbelos.';
        recalc();
    });

    // Fecha del evento → entrega y recogida (fin de semana: de viernes a domingo)
    (() => {
        const ev = document.getElementById('f-event_date'), st = document.getElementById('f-start_date'), en = document.getElementById('f-end_date');
        let auto = {start: '', end: ''};
        const ymd = d => d.toISOString().slice(0, 10);
        ev.addEventListener('change', () => {
            if (!ev.value) return;
            const d = new Date(ev.value + 'T12:00:00Z'), dow = d.getUTCDay(); // 0 domingo, 5 viernes, 6 sábado
            let s = new Date(d), e = new Date(d);
            if ([5, 6, 0].includes(dow)) {
                s.setUTCDate(d.getUTCDate() - (dow === 0 ? 2 : dow - 5));
                e = new Date(s); e.setUTCDate(s.getUTCDate() + 2);
            }
            if (!st.value || st.value === auto.start || st.value > ymd(s)) st.value = auto.start = ymd(s);
            if (!en.value || en.value === auto.end || en.value < ymd(e)) en.value = auto.end = ymd(e);
        });
    })();

    // Al elegir cliente se propone el descuento de su tipo
    document.getElementById('f-client_id').addEventListener('change', e => {
        const d = e.target.selectedOptions[0]?.dataset.discount;
        if (d !== undefined) { document.getElementById('f-discount_percent').value = parseFloat(d); recalc(); }
    });

    const cpInput = document.getElementById('f-destination_postal_code');
    cpInput.addEventListener('input', async () => {
        const cp = cpInput.value.replace(/\D/g, '');
        const hint = document.getElementById('cp-hint');
        if (cp.length !== 5) return;
        try {
            const data = await (await fetch(@json(url('app/postal-codes')) + '/' + cp, {headers: {Accept: 'application/json'}})).json();
            if (!data.found) { hint.className = 'err'; hint.textContent = 'Código postal no encontrado.'; return; }
            hint.className = 'hint';
            hint.textContent = data.municipality + (data.province ? ' (' + data.province + ')' : '')
                + (data.places.length > 1 ? ' · ' + data.places.length + ' localidades con este CP' : '');
            document.getElementById('cp-places').innerHTML = data.places.map(p => '<option value="' + p.replace(/"/g, '&quot;') + '">').join('');
            const city = document.getElementById('f-destination_city');
            if (!city.value.trim()) city.value = data.places.length === 1 ? data.places[0] : data.municipality;
        } catch (e) {}
    });

    const calcBtn = document.getElementById('calc-km');
    if (calcBtn) calcBtn.addEventListener('click', async () => {
        const hint = document.getElementById('km-hint');
        const params = new URLSearchParams({
            address: document.getElementById('f-destination_address').value,
            city: document.getElementById('f-destination_city').value,
            postal_code: document.getElementById('f-destination_postal_code').value,
        });
        calcBtn.disabled = true; hint.className = 'hint'; hint.textContent = 'Calculando…';
        try {
            const res = await fetch(@json(route('orders.distance')) + '?' + params, {headers: {Accept: 'application/json'}});
            const data = await res.json();
            if (!res.ok) throw new Error(data.error || 'No se ha podido calcular.');
            document.getElementById('f-distance_km').value = data.km;
            hint.textContent = 'Ruta hasta: ' + data.label + '. Comprueba que es el sitio correcto.';
            recalc();
        } catch (e) {
            hint.className = 'err'; hint.textContent = e.message;
        } finally {
            calcBtn.disabled = false;
        }
    });
    recalc();
})();
</script>
@endsection
