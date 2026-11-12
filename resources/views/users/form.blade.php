@extends('layouts.app')
@section('title', $user->exists ? 'Editar usuario' : 'Nuevo usuario')
@section('content')
@php($self = $user->exists && $user->is(auth()->user()))
<div class="head"><h1>{{ $user->exists ? 'Editar usuario' : 'Nuevo usuario' }}</h1></div>
<form class="card" method="post" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" style="max-width:560px">
    @csrf @if ($user->exists) @method('put') @endif
    @include('partials.field', ['name' => 'name', 'label' => 'Nombre', 'value' => $user->name, 'required' => true])
    @include('partials.field', ['name' => 'email', 'label' => 'Email', 'value' => $user->email, 'type' => 'email', 'required' => true])
    <div class="field">
        <label for="f-password">Contraseña @unless ($user->exists)*@endunless</label>
        <input id="f-password" type="password" name="password" autocomplete="new-password" @required(!$user->exists)>
        <div class="hint">Mínimo 8 caracteres.@if ($user->exists) Déjalo vacío para no cambiarla.@endif</div>
        @error('password')<div class="err">{{ $message }}</div>@enderror
    </div>
    <div class="field">
        <label for="f-password_confirmation">Repite la contraseña</label>
        <input id="f-password_confirmation" type="password" name="password_confirmation" autocomplete="new-password">
    </div>
    <div class="field"><label class="check"><input type="checkbox" name="is_superadmin" value="1" @checked(old('is_superadmin', $user->is_superadmin)) @disabled($self)> Superusuario (gestiona usuarios)</label></div>
    <div class="field"><label class="check"><input type="checkbox" name="active" value="1" @checked(old('active', $user->active)) @disabled($self)> Activo (puede iniciar sesión)</label></div>
    <div class="actions">
        <button class="btn">Guardar</button>
        <a class="btn sec" href="{{ route('users.index') }}">Cancelar</a>
    </div>
</form>
@if ($user->exists && !$self)
    <form method="post" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('¿Eliminar este usuario?')">
        @csrf @method('delete')<button class="btn danger sm">Eliminar usuario</button>
    </form>
@endif
@endsection
