@extends('layouts.app')
@section('title', $venue->exists ? 'Editar finca' : 'Nueva finca')
@section('content')
<div class="head"><h1>{{ $venue->exists ? 'Editar finca' : 'Nueva finca' }}</h1></div>
<form class="card" method="post" action="{{ $venue->exists ? route('venues.update', $venue) : route('venues.store') }}" style="max-width:760px">
    @csrf @if ($venue->exists) @method('put') @endif
    @include('partials.field', ['name' => 'name', 'label' => 'Nombre', 'value' => $venue->name, 'required' => true, 'hint' => 'Ej.: Finca La Concepción, Hacienda Nadales…'])
    @include('partials.field', ['name' => 'address', 'label' => 'Dirección', 'value' => $venue->address])
    <div class="grid" style="grid-template-columns:140px 1fr;gap:10px">
        @include('partials.field', ['name' => 'postal_code', 'label' => 'C. postal', 'value' => $venue->postal_code, 'attrs' => 'inputmode=numeric maxlength=5 autocomplete=off data-cp-city=f-city'])
        @include('partials.field', ['name' => 'city', 'label' => 'Población', 'value' => $venue->city])
    </div>
    <div class="field">
        <label for="f-distance_km">Distancia desde la base (km, solo ida)</label>
        <div style="display:flex;gap:8px;max-width:360px">
            <input id="f-distance_km" type="number" name="distance_km" step="1" min="0" value="{{ old('distance_km', $venue->distance_km) }}">
            @if ($mapsEnabled)<button type="button" class="btn sec" id="calc-km" style="white-space:nowrap">Calcular km</button>@endif
        </div>
        <div class="hint" id="km-hint">{{ $mapsEnabled ? 'Si lo dejas vacío se calcula al guardar. Puedes corregirlo a mano.' : 'Escríbelo a mano.' }}</div>
        @error('distance_km')<div class="err">{{ $message }}</div>@enderror
    </div>
    @include('partials.field', ['name' => 'contact', 'label' => 'Contacto de la finca', 'value' => $venue->contact, 'hint' => 'Persona, teléfono…'])
    @include('partials.field', ['name' => 'notes', 'label' => 'Notas', 'value' => $venue->notes, 'type' => 'textarea', 'hint' => 'Accesos para el camión, horarios de montaje, restricciones…'])
    <div class="field"><label class="check"><input type="checkbox" name="active" value="1" @checked(old('active', $venue->active))> Activa (aparece al crear pedidos)</label></div>
    <div class="actions">
        <button class="btn">Guardar</button>
        <a class="btn sec" href="{{ route('venues.index') }}">Cancelar</a>
    </div>
</form>
@if ($venue->exists)
    <form method="post" action="{{ route('venues.destroy', $venue) }}" onsubmit="return confirm('¿Eliminar esta finca? Los pedidos que la usaron conservan su dirección.')">
        @csrf @method('delete')<button class="btn danger sm">Eliminar finca</button>
    </form>
@endif
@if ($mapsEnabled)
<script>
document.getElementById('calc-km').addEventListener('click', async e => {
    const btn = e.currentTarget, hint = document.getElementById('km-hint');
    const params = new URLSearchParams({
        address: document.getElementById('f-address').value,
        city: document.getElementById('f-city').value,
        postal_code: document.getElementById('f-postal_code').value,
    });
    btn.disabled = true; hint.className = 'hint'; hint.textContent = 'Calculando…';
    try {
        const res = await fetch(@json(route('orders.distance')) + '?' + params, {headers: {Accept: 'application/json'}});
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || 'No se ha podido calcular.');
        document.getElementById('f-distance_km').value = data.km;
        hint.textContent = 'Ruta hasta: ' + data.label + '.';
    } catch (err) { hint.className = 'err'; hint.textContent = err.message; }
    finally { btn.disabled = false; }
});
</script>
@endif
@endsection
