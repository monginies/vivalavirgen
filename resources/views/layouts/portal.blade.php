<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@hasSection('title')@yield('title') · @endif{{ config('app.name') }} · Alquiler de decoración mediterránea</title>
    <meta name="description" content="Alquiler de mobiliario y decoración mediterránea para bodas y eventos en Málaga.">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=9">
    <link rel="icon" type="image/png" href="{{ asset('img/favicon-32.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('img/apple-touch-icon.png') }}">
    <meta name="theme-color" content="#9a9b88">
</head>
<body class="portal">
@php($me = auth('client')->user())
@php($cartCount = \App\Support\Cart::count())
<header class="pt-head">
    <a class="pt-brand" href="{{ route('portal.home') }}"><img src="{{ asset('img/logo-96.png') }}" alt="Viva la Virgen"><span>Viva la Virgen<small>Alquiler de decoración mediterránea</small></span></a>
    <nav class="pt-nav">
        <a href="{{ route('portal.home') }}" @class(['on' => request()->routeIs('portal.home', 'portal.article')])>Catálogo</a>
        @if ($me)
            <a href="{{ route('portal.account') }}" @class(['on' => request()->routeIs('portal.account', 'portal.orders.*')])>Mi cuenta</a>
            <form method="post" action="{{ route('portal.logout') }}">@csrf<button class="linklike">Salir</button></form>
        @else
            <a href="{{ route('portal.login') }}" @class(['on' => request()->routeIs('portal.login')])>Entrar</a>
            <a href="{{ route('portal.register') }}" @class(['on' => request()->routeIs('portal.register')])>Crear cuenta</a>
        @endif
        <a class="pt-cart @if (request()->routeIs('portal.cart')) on @endif" href="{{ route('portal.cart') }}" title="Mi pedido">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
            <span>Mi pedido</span>@if ($cartCount)<b class="pt-count">{{ $cartCount }}</b>@endif
        </a>
    </nav>
</header>
@if ($me && ! $me->isApproved())
    <div class="pt-banner">
        @if (! $me->hasVerifiedEmail())
            Confirma tu email con el enlace que te hemos enviado para que podamos activar tu cuenta.
            <form method="post" action="{{ route('portal.verification.send') }}" style="display:inline">@csrf<button class="linklike">Reenviar email</button></form>
        @else
            Tu cuenta está pendiente de aprobación. Puedes preparar tu pedido; te avisaremos por email cuando puedas ver precios y enviarlo.
        @endif
    </div>
@endif
<main class="pt-main">
    @if (session('status'))<div class="flash ok">{{ session('status') }}</div>@endif
    @if (session('error'))<div class="flash ko">{{ session('error') }}</div>@endif
    @yield('content')
</main>
<footer class="pt-foot">
    <img src="{{ asset('img/logo-96.png') }}" alt="" width="48" height="48">
    <div>
        <b>Viva la Virgen</b> · Alquiler de decoración mediterránea<br>
        C/ Driza, 13 · 29006 Málaga · <a href="mailto:gestion@vivalavirgen.net">gestion@vivalavirgen.net</a>
    </div>
    <a href="{{ route('portal.privacy') }}">Política de privacidad</a>
</footer>
<script>
// Código postal → población (campos con data-cp-city)
document.querySelectorAll('input[data-cp-city]').forEach(cp => {
    const city = document.getElementById(cp.dataset.cpCity);
    const prov = cp.dataset.cpProvince ? document.getElementById(cp.dataset.cpProvince) : null;
    if (!city) return;
    let auto = '';
    cp.addEventListener('input', async () => {
        const code = cp.value.replace(/\D/g, '');
        if (code.length !== 5) return;
        try {
            const data = await (await fetch(@json(url('codigo-postal')) + '/' + code, {headers: {Accept: 'application/json'}})).json();
            if (!data.found) return;
            if (!city.value.trim() || city.value === auto) city.value = auto = data.places.length === 1 ? data.places[0] : (data.municipality || data.places[0]);
            if (prov && !prov.value.trim()) prov.value = data.province || '';
            city.dispatchEvent(new Event('input', {bubbles: true}));
        } catch (e) {}
    });
});
</script>
@stack('scripts')
</body>
</html>
