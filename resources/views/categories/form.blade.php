@extends('layouts.app')
@section('title', $category->exists ? 'Editar categoría' : 'Nueva categoría')
@section('content')
<div class="head"><h1>{{ $category->exists ? 'Editar categoría' : 'Nueva categoría' }}</h1></div>
<form class="card" method="post" enctype="multipart/form-data" action="{{ $category->exists ? route('categories.update', $category) : route('categories.store') }}" style="max-width:640px">
    @csrf @if ($category->exists) @method('put') @endif
    @include('partials.field', ['name' => 'name', 'label' => 'Nombre', 'value' => $category->name, 'required' => true])
    @include('partials.field', ['name' => 'code', 'label' => 'Código', 'value' => $category->code, 'required' => true,
        'hint' => 'Prefijo corto (letras/números) para las referencias. Ej.: SIL → SIL-0001. Cambiarlo no renombra artículos existentes.', 'attrs' => 'maxlength=10 style=text-transform:uppercase'])
    @include('partials.field', ['name' => 'description', 'label' => 'Descripción', 'value' => $category->description, 'type' => 'textarea', 'hint' => 'Se muestra en la web, debajo del nombre de la colección.'])
    <div class="field">
        <label for="f-cover">Foto de portada en la web</label>
        @if ($category->cover_path)
            <img src="{{ asset('storage/'.$category->cover_path) }}" alt="" style="max-width:320px;border-radius:8px;display:block;margin-bottom:8px">
            <label class="check"><input type="checkbox" name="remove_cover" value="1"> Quitar la foto</label>
        @endif
        <input id="f-cover" type="file" name="cover" accept="image/*">
        <div class="hint">Foto horizontal de ambiente. Si no pones ninguna, se usa la foto principal de su primer artículo.</div>
        @error('cover')<div class="err">{{ $message }}</div>@enderror
    </div>
    @include('partials.field', ['name' => 'sort', 'label' => 'Orden en la web', 'value' => $category->sort ?? 0, 'type' => 'number', 'step' => 1, 'min' => 0, 'hint' => 'Las de número más bajo salen primero.'])
    <div class="actions">
        <button class="btn">Guardar</button>
        <a class="btn sec" href="{{ route('categories.index') }}">Cancelar</a>
    </div>
</form>
@if ($category->exists)
    <form method="post" action="{{ route('categories.destroy', $category) }}" onsubmit="return confirm('¿Eliminar esta categoría?')">
        @csrf @method('delete')<button class="btn danger sm">Eliminar categoría</button>
    </form>
@endif
@endsection
