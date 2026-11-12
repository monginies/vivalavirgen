@extends('layouts.portal')
@section('title', 'Entrar')
@section('content')
<div class="card pt-narrow">
    <h1>Entrar</h1>
    <p class="muted">Accede para ver tus precios y enviar pedidos.</p>
    <form method="post" action="{{ route('portal.login') }}">
        @csrf
        @include('partials.field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'attrs' => 'autofocus autocomplete=username'])
        <div class="field">
            <label for="f-password">Contraseña</label>
            <input id="f-password" type="password" name="password" required autocomplete="current-password">
        </div>
        <div class="field"><label class="check"><input type="checkbox" name="remember" value="1"> Recordarme en este dispositivo</label></div>
        <button class="btn" style="width:100%;justify-content:center">Entrar</button>
    </form>
    <p style="margin-top:16px;font-size:14px"><a href="{{ route('portal.password.request') }}">He olvidado mi contraseña</a> · ¿No tienes cuenta? <a href="{{ route('portal.register') }}">Crea una</a></p>
</div>
@endsection
