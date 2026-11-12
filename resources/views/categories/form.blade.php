@extends('layouts.app')
@section('title', $category->exists ? 'Editar categoría' : 'Nueva categoría')
@section('content')
<div class="head"><h1>{{ $category->exists ? 'Editar categoría' : 'Nueva categoría' }}</h1></div>
<form class="card" method="post" action="{{ $category->exists ? route('categories.update', $category) : route('categories.store') }}" style="max-width:640px">
    @csrf @if ($category->exists) @method('put') @endif
    @include('partials.field', ['name' => 'name', 'label' => 'Nombre', 'value' => $category->name, 'required' => true])
    @include('partials.field', ['name' => 'code', 'label' => 'Código', 'value' => $category->code, 'required' => true,
        'hint' => 'Prefijo corto (letras/números) para las referencias. Ej.: SIL → SIL-0001. Cambiarlo no renombra artículos existentes.', 'attrs' => 'maxlength=10 style=text-transform:uppercase'])
    @include('partials.field', ['name' => 'description', 'label' => 'Descripción', 'value' => $category->description, 'type' => 'textarea'])
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
