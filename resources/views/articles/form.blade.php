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
    <div class="field">
        <label for="f-photo">Foto</label>
        @if ($article->photoUrl())
            <img class="thumb" style="width:90px;height:90px" src="{{ $article->photoUrl() }}" alt="">
            <label class="check" style="margin:6px 0"><input type="checkbox" name="remove_photo" value="1"> Quitar foto actual</label>
        @endif
        <input id="f-photo" type="file" name="photo" accept="image/*">
        <div class="hint">JPG/PNG/WebP, máx. 5 MB.</div>
        @error('photo')<div class="err">{{ $message }}</div>@enderror
    </div>
    @include('partials.field', ['name' => 'notes', 'label' => 'Notas internas', 'value' => $article->notes, 'type' => 'textarea'])
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
@if ($article->exists)
    <form method="post" action="{{ route('articles.destroy', $article) }}" onsubmit="return confirm('¿Eliminar el artículo y todas sus unidades?')">
        @csrf @method('delete')<button class="btn danger sm">Eliminar artículo</button>
    </form>
@endif
@endsection
