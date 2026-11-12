@extends('layouts.app')
@section('title', 'Entrar')
@section('content')
<div class="login card">
    <h1 style="margin-bottom:4px">{{ config('app.name') }}</h1>
    <p class="muted" style="margin-top:0">Accede con tu cuenta</p>
    <form method="post" action="{{ route('login') }}">
        @csrf
        @include('partials.field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'attrs' => 'autofocus autocomplete=username'])
        <div class="field">
            <label for="f-password">Contraseña</label>
            <input id="f-password" type="password" name="password" required autocomplete="current-password">
        </div>
        <div class="field"><label class="check"><input type="checkbox" name="remember" value="1"> Recordarme</label></div>
        <button class="btn" style="width:100%;justify-content:center">Entrar</button>
    </form>
</div>
@endsection
