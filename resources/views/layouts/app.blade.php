<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Inicio') · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=6">
    <link rel="icon" type="image/png" href="{{ asset('img/favicon-32.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('img/apple-touch-icon.png') }}">
    <meta name="theme-color" content="#9a9b88">
</head>
<body>
@auth
<header class="topbar">
    <a class="brand" href="{{ route('dashboard') }}" title="{{ config('app.name') }} · Inicio"><img src="{{ asset('img/logo-96.png') }}" alt="{{ config('app.name') }}"></a>
    <nav>
        <a href="{{ route('dashboard') }}" title="Inicio" @class(['on' => request()->routeIs('dashboard')])>@include('partials.icon', ['i' => 'home'])<span>Inicio</span></a>
        <a href="{{ route('orders.index') }}" title="Pedidos" @class(['on' => request()->routeIs('orders.*')])>@include('partials.icon', ['i' => 'orders'])<span>Pedidos</span></a>
        <a href="{{ route('calendar') }}" title="Calendario" @class(['on' => request()->routeIs('calendar')])>@include('partials.icon', ['i' => 'calendar'])<span>Calendario</span></a>
        <a href="{{ route('availability') }}" title="Disponibilidad" @class(['on' => request()->routeIs('availability')])>@include('partials.icon', ['i' => 'availability'])<span>Disponibilidad</span></a>
        <a href="{{ route('articles.index') }}" title="Artículos" @class(['on' => request()->routeIs('articles.*', 'units.*')])>@include('partials.icon', ['i' => 'articles'])<span>Artículos</span></a>
        <a href="{{ route('categories.index') }}" title="Categorías" @class(['on' => request()->routeIs('categories.*')])>@include('partials.icon', ['i' => 'categories'])<span>Categorías</span></a>
        <a href="{{ route('clients.index') }}" title="Clientes" @class(['on' => request()->routeIs('clients.*')])>@include('partials.icon', ['i' => 'clients'])<span>Clientes</span></a>
        <a href="{{ route('venues.index') }}" title="Fincas" @class(['on' => request()->routeIs('venues.*')])>@include('partials.icon', ['i' => 'venues'])<span>Fincas</span></a>
        @if (auth()->user()->is_superadmin)
            <a href="{{ route('users.index') }}" title="Usuarios" @class(['on' => request()->routeIs('users.*')])>@include('partials.icon', ['i' => 'users'])<span>Usuarios</span></a>
            <a href="{{ route('settings.edit') }}" title="Ajustes" @class(['on' => request()->routeIs('settings.*')])>@include('partials.icon', ['i' => 'settings'])<span>Ajustes</span></a>
        @endif
    </nav>
    <div class="who">
        {{ auth()->user()->name }}
        <form method="post" action="{{ route('logout') }}">@csrf<button title="Cerrar sesión">@include('partials.icon', ['i' => 'logout'])<span>Salir</span></button></form>
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
