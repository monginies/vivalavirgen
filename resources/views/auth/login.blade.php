@extends('layouts.app')
@section('title', 'Entrar')
@section('content')
<div class="login card">
    <img class="logo" src="{{ asset('img/logo-320.png') }}" alt="{{ config('app.name') }} · Alquiler Decoración Mediterránea">
    <p class="tagline">Gestión de inventario y pedidos</p>
    <form method="post" action="{{ route('login') }}">
        @csrf
        @include('partials.field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'attrs' => 'autofocus autocomplete=username'])
        <div class="field">
            <label for="f-password">Contraseña</label>
            <input id="f-password" type="password" name="password" required autocomplete="current-password">
        </div>
        <div class="field"><label class="check"><input type="checkbox" name="remember" value="1" @checked(old('remember', true))> Recordarme</label></div>
        <button class="btn" style="width:100%;justify-content:center">Entrar</button>
    </form>
</div>
@endsection
