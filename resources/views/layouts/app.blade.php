<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Inicio') · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=2">
</head>
<body>
@auth
<header class="topbar">
    <a class="brand" href="{{ route('dashboard') }}">{{ config('app.name') }}</a>
    <nav>
        <a href="{{ route('dashboard') }}" @class(['on' => request()->routeIs('dashboard')])>Inicio</a>
        <a href="{{ route('orders.index') }}" @class(['on' => request()->routeIs('orders.*')])>Pedidos</a>
        <a href="{{ route('calendar') }}" @class(['on' => request()->routeIs('calendar')])>Calendario</a>
        <a href="{{ route('availability') }}" @class(['on' => request()->routeIs('availability')])>Disponibilidad</a>
        <a href="{{ route('articles.index') }}" @class(['on' => request()->routeIs('articles.*', 'units.*')])>Artículos</a>
        <a href="{{ route('categories.index') }}" @class(['on' => request()->routeIs('categories.*')])>Categorías</a>
        <a href="{{ route('clients.index') }}" @class(['on' => request()->routeIs('clients.*')])>Clientes</a>
        @if (auth()->user()->is_superadmin)
            <a href="{{ route('users.index') }}" @class(['on' => request()->routeIs('users.*')])>Usuarios</a>
            <a href="{{ route('settings.edit') }}" @class(['on' => request()->routeIs('settings.*')])>Ajustes</a>
        @endif
    </nav>
    <div class="who">
        {{ auth()->user()->name }}
        <form method="post" action="{{ route('logout') }}">@csrf<button>Salir</button></form>
    </div>
</header>
@endauth
<main>
    @if (session('status'))<div class="flash ok">{{ session('status') }}</div>@endif
    @if (session('error'))<div class="flash ko">{{ session('error') }}</div>@endif
    @yield('content')
</main>
</body>
</html>
