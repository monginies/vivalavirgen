@extends('layouts.app')
@section('title', $category->exists ? 'Editar tipo de cliente' : 'Nuevo tipo de cliente')
@section('content')
<div class="head"><h1>{{ $category->exists ? 'Editar tipo de cliente' : 'Nuevo tipo de cliente' }}</h1></div>
<form class="card" method="post" action="{{ $category->exists ? route('client-categories.update', $category) : route('client-categories.store') }}" style="max-width:520px">
    @csrf @if ($category->exists) @method('put') @endif
    @include('partials.field', ['name' => 'name', 'label' => 'Nombre', 'value' => $category->name, 'required' => true])
    @include('partials.field', ['name' => 'discount_percent', 'label' => 'Descuento (%)', 'value' => $category->discount_percent, 'type' => 'number', 'step' => '0.01', 'min' => 0, 'required' => true])
    @include('partials.field', ['name' => 'sort', 'label' => 'Orden', 'value' => $category->sort, 'type' => 'number', 'step' => 1, 'min' => 0])
    <div class="actions"><button class="btn">Guardar</button><a class="btn sec" href="{{ route('client-categories.index') }}">Cancelar</a></div>
</form>
@if ($category->exists)
    <form method="post" action="{{ route('client-categories.destroy', $category) }}" onsubmit="return confirm('¿Eliminar este tipo? Sus clientes quedarán sin descuento.')">@csrf @method('delete')<button class="btn danger sm">Eliminar</button></form>
@endif
@endsection
