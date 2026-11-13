@extends('layouts.app')
@section('title', $article->exists ? 'Editar artículo' : 'Nuevo artículo')
@section('content')
<div class="head">
    <div><h1>{{ $article->exists ? 'Editar artículo' : 'Nuevo artículo' }}</h1>
        @if ($article->exists)<div class="sub mono">{{ $article->reference }}</div>@endif</div>
</div>
<form class="card" method="post" enctype="multipart/form-data" action="{{ $article->exists ? route('articles.update', $article) : route('articles.store') }}" style="max-width:760px">
    @csrf @if ($article->exists) @method('put') @endif
    <div class="field">
        <label for="f-category_id">Categoría *</label>
        <select id="f-category_id" name="category_id" required>
            <option value="">— Elige —</option>
            @foreach ($categories as $c)
                <option value="{{ $c->id }}" @selected(old('category_id', $article->category_id) == $c->id)>{{ $c->name }} ({{ $c->code }})</option>
            @endforeach
        </select>
        @unless ($article->exists)<div class="hint">La referencia se genera automáticamente con el código de la categoría.</div>@endunless
        @error('category_id')<div class="err">{{ $message }}</div>@enderror
    </div>
    @include('partials.field', ['name' => 'name', 'label' => 'Nombre', 'value' => $article->name, 'required' => true])
    @include('partials.field', ['name' => 'description', 'label' => 'Descripción', 'value' => $article->description, 'type' => 'textarea'])
    @include('partials.field', ['name' => 'dimensions', 'label' => 'Medidas', 'value' => $article->dimensions, 'hint' => 'Ej.: 45 × 50 × 80 cm'])
    <div class="grid g2">
        @include('partials.field', ['name' => 'rental_price', 'label' => 'Precio de alquiler (€, sin IVA)', 'value' => $article->rental_price ?? 0, 'type' => 'number', 'step' => '0.01', 'min' => 0, 'required' => true])
        @include('partials.field', ['name' => 'replacement_price', 'label' => 'Coste de reposición por unidad (€)', 'value' => $article->replacement_price ?? 0, 'type' => 'number', 'step' => '0.01', 'min' => 0, 'required' => true])
    </div>
    @if ($article->exists)
        <div class="field">
            <label>Fotos</label>
            <div class="hint" style="margin:0">Las fotos se añaden, ordenan y eliminan en la <a href="{{ route('articles.show', $article) }}#photos-card">ficha del artículo</a>.</div>
        </div>
    @else
        <div class="field">
            <label for="f-photos">Fotos</label>
            <input id="f-photos" type="file" name="photos[]" accept="image/*" multiple>
            <div class="hint">Puedes elegir varias a la vez (hasta 20). La primera será la principal. Después podrás añadir más desde la ficha.</div>
            @error('photos')<div class="err">{{ $message }}</div>@enderror
            @error('photos.*')<div class="err">{{ $message }}</div>@enderror
        </div>
    @endif
    @include('partials.field', ['name' => 'notes', 'label' => 'Notas internas', 'value' => $article->notes, 'type' => 'textarea'])
    @php
        $isBundle = (bool) old('is_bundle', $article->is_bundle);
        $rows = old('components', $article->components->map(fn ($c) => ['article_id' => $c->id, 'quantity' => $c->pivot->quantity])->all());
        if (! $rows) { $rows = [['article_id' => '', 'quantity' => 1]]; }
    @endphp
    <div class="field">
        <label class="check"><input type="hidden" name="is_bundle" value="0"><input type="checkbox" name="is_bundle" value="1" id="f-is_bundle" @checked($isBundle)> Producto combinado (formado por varios artículos)</label>
        <div class="hint">No tiene unidades propias. Al alquilarlo se reservan sus piezas, que dejan de estar disponibles sueltas en esas fechas. Su precio es el de esta ficha, no la suma de las piezas.</div>
        @error('is_bundle')<div class="err">{{ $message }}</div>@enderror
    </div>
    <div class="field" id="bundle-parts" @if (! $isBundle) hidden @endif>
        <label>Piezas del combinado</label>
        @error('components')<div class="err">{{ $message }}</div>@enderror
        @foreach (collect($errors->get('components.*'))->flatten() as $m)<div class="err">{{ $m }}</div>@endforeach
        <table id="parts">
            <thead><tr><th>Artículo</th><th class="num" style="width:110px">Cantidad</th><th style="width:40px"></th></tr></thead>
            <tbody>
            @foreach ($rows as $i => $row)
                <tr class="part">
                    <td>
                        <select name="components[{{ $i }}][article_id]">
                            <option value="">—</option>
                            @foreach ($componentOptions->groupBy(fn ($a) => $a->category->name) as $cat => $group)
                                <optgroup label="{{ $cat }}">
                                @foreach ($group as $a)
                                    <option value="{{ $a->id }}" @selected($row['article_id'] == $a->id)>{{ $a->reference }} · {{ $a->name }}@unless ($a->active) (inactivo)@endunless</option>
                                @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </td>
                    <td><input type="number" name="components[{{ $i }}][quantity]" min="1" max="1000" step="1" value="{{ $row['quantity'] }}"></td>
                    <td><button type="button" class="btn sec sm p-del" title="Quitar">×</button></td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div class="actions" style="margin-top:8px"><button type="button" class="btn sec sm" id="add-part">+ Añadir pieza</button></div>
    </div>
    @php
        $isExclusive = (bool) old('exclusive', $article->exclusive);
        $chosen = collect(old('clients', $article->exists ? $article->clients->pluck('id')->all() : []))->map(fn ($v) => (int) $v)->all();
    @endphp
    <div class="field">
        <label class="check"><input type="hidden" name="exclusive" value="0"><input type="checkbox" name="exclusive" value="1" id="f-exclusive" @checked($isExclusive)> Alquiler exclusivo</label>
        <div class="hint">Solo lo pueden alquilar los clientes que elijas. Los demás lo ven en la web, pero sin disponibilidad, como si ya estuviera alquilado.</div>
    </div>
    <div class="field" id="exclusive-clients" @if (! $isExclusive) hidden @endif>
        <label for="f-clients">Clientes autorizados</label>
        <select id="f-clients" name="clients[]" multiple size="{{ min(10, max(4, $clientOptions->count())) }}">
            @foreach ($clientOptions as $c)
                <option value="{{ $c->id }}" @selected(in_array($c->id, $chosen, true))>{{ $c->name }}@if ($c->trade_name) ({{ $c->trade_name }})@endif</option>
            @endforeach
        </select>
        <div class="hint">Mantén pulsada Cmd (Mac) o Ctrl (Windows) para elegir varios. Si no eliges ninguno, nadie lo puede alquilar.</div>
        @error('clients')<div class="err">{{ $message }}</div>@enderror
        @error('clients.*')<div class="err">{{ $message }}</div>@enderror
    </div>
    @unless ($article->exists)
        @include('partials.field', ['name' => 'initial_units', 'label' => 'Unidades iniciales', 'value' => 0, 'type' => 'number', 'step' => 1, 'min' => 0,
            'hint' => 'Se crearán con su código y QR. Puedes añadir más después.'])
    @endunless
    <div class="field"><label class="check"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked(old('active', $article->active))> Activo (disponible para alquilar)</label></div>
    <div class="actions">
        <button class="btn">Guardar</button>
        <a class="btn sec" href="{{ $article->exists ? route('articles.show', $article) : route('articles.index') }}">Cancelar</a>
    </div>
</form>
<script>
(() => {
    const ex = document.getElementById('f-exclusive'), exBox = document.getElementById('exclusive-clients');
    ex.addEventListener('change', () => exBox.hidden = !ex.checked);
    const box = document.getElementById('f-is_bundle'), parts = document.getElementById('bundle-parts');
    const units = document.getElementById('f-initial_units')?.closest('.field');
    const sync = () => { parts.hidden = !box.checked; if (units) units.hidden = box.checked; };
    box.addEventListener('change', sync); sync();
    const tbody = document.querySelector('#parts tbody');
    let next = tbody.rows.length;
    document.getElementById('add-part').addEventListener('click', () => {
        const tr = tbody.rows[0].cloneNode(true);
        tr.querySelectorAll('select,input').forEach(el => {
            el.name = el.name.replace(/components\[\d+\]/, 'components[' + next + ']');
            el.value = el.tagName === 'INPUT' ? 1 : '';
        });
        next++;
        tbody.appendChild(tr);
    });
    tbody.addEventListener('click', e => {
        if (!e.target.classList.contains('p-del')) return;
        if (tbody.rows.length > 1) e.target.closest('tr').remove();
        else e.target.closest('tr').querySelectorAll('select,input').forEach(el => el.value = el.tagName === 'INPUT' ? 1 : '');
    });
})();
</script>
@if ($article->exists)
    <form method="post" action="{{ route('articles.destroy', $article) }}" onsubmit="return confirm('¿Eliminar el artículo y todas sus unidades?')">
        @csrf @method('delete')<button class="btn danger sm">Eliminar artículo</button>
    </form>
@endif
@endsection
