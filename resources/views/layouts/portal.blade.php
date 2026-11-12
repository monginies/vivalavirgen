<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        // Las secciones de Blade ya vienen escapadas: se imprimen con {!! !!}
        $seoTitle = trim($__env->yieldContent('title'));
        $seoTitle = $seoTitle !== '' ? $seoTitle.' · '.e(config('app.name')) : e(config('app.name').' · Alquiler de decoración mediterránea en Málaga');
        $seoDesc = trim($__env->yieldContent('description')) ?: e('Alquiler de mobiliario y decoración mediterránea para bodas y eventos en Málaga y provincia: atriles, alfombras, bancos, salones, faroles y mucho más.');
        $heroSeo = \App\Models\Setting::get('hero_image');
        $seoImg = trim($__env->yieldContent('og_image')) ?: e($heroSeo ? asset('storage/'.$heroSeo) : asset('img/logo-320.png'));
        $seoUrl = e(url()->current());
    @endphp
    <title>{!! $seoTitle !!}</title>
    <meta name="description" content="{!! $seoDesc !!}">
    @hasSection('robots')<meta name="robots" content="@yield('robots')">@endif
    <link rel="canonical" href="{!! $seoUrl !!}">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:locale" content="es_ES">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{!! $seoTitle !!}">
    <meta property="og:description" content="{!! $seoDesc !!}">
    <meta property="og:url" content="{!! $seoUrl !!}">
    <meta property="og:image" content="{!! $seoImg !!}">
    <meta name="twitter:card" content="summary_large_image">
    @stack('jsonld')
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=36">
    <link rel="icon" type="image/png" href="{{ asset('img/favicon-32.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('img/apple-touch-icon.png') }}">
    <meta name="theme-color" content="#9a9b88">
    @if ($gsv = \App\Models\Setting::get('google_site_verification'))<meta name="google-site-verification" content="{{ $gsv }}">@endif
</head>
<body class="portal">
@php($me = auth('client')->user())
@php($cartCount = \App\Support\Cart::count())
@php($cart = \App\Support\Cart::get())
<header class="pt-head">
    <a class="pt-brand" href="{{ route('portal.home') }}">
        <img class="pt-logo" src="{{ asset('img/logo-320.png') }}" alt="Viva la Virgen">
        <span class="pt-word">Viva la Virgen</span>
        <span class="pt-tag">Alquiler decoración mediterránea</span>
    </a>
    <nav class="pt-nav">
        @if ($me)
            <a class="pt-pill" href="{{ route('portal.account') }}">Mi cuenta</a>
            <form method="post" action="{{ route('portal.logout') }}" style="margin:0">@csrf
                <button class="pt-pill pt-logout" title="Cerrar sesión"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg><span>Salir</span></button>
            </form>
        @else
            <a class="pt-pill" href="{{ route('portal.login') }}">Ingresar</a>
        @endif
        <a class="pt-pill dark" href="{{ route('portal.cart') }}">
            <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
            Pedido ({{ $cartCount }})
        </a>
    </nav>
</header>
@unless (request()->routeIs('portal.cart', 'portal.login', 'portal.register', 'portal.password.*', 'portal.privacy'))
<form @class(['pt-plan', 'needs-date' => ! $cart['event_date']]) method="post" action="{{ route('portal.cart.date') }}">
    @csrf
    <div>
        <b>Planifica tu evento</b>
        <span>Indica la fecha de tu evento y verás las unidades disponibles de cada artículo.</span>
    </div>
    @unless ($cart['event_date'])<span class="pt-track" aria-hidden="true"><span></span><span></span><span></span></span>@endunless
    <label>Fecha del evento <input type="date" name="event_date" min="{{ today()->toDateString() }}" value="{{ $cart['event_date'] }}" onchange="this.form.submit()"></label>
</form>
@endunless
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
@if (session('status') || session('error'))
    <div class="pt-toasts" aria-live="polite">
        @if (session('status'))<div class="pt-toast ok" data-ttl="5000">{{ session('status') }}<button type="button" aria-label="Cerrar">×</button></div>@endif
        @if (session('error'))<div class="pt-toast ko" data-ttl="9000">{{ session('error') }}<button type="button" aria-label="Cerrar">×</button></div>@endif
    </div>
@endif
@yield('full')
@hasSection('content')
<main class="pt-main">@yield('content')</main>
@endif
<footer class="pt-foot">
    <div class="pt-foot-in">
        <div>
            <img class="pt-foot-logo" src="{{ asset('img/logo-320.png') }}" alt="Viva la Virgen">
        </div>
        <div>C/ Driza, 13 · 29006 Málaga<br><a href="mailto:gestion@vivalavirgen.net">gestion@vivalavirgen.net</a></div>
        <div>
            @if ($me)
                <a href="{{ route('portal.account') }}">Mi cuenta</a> ·
                <form method="post" action="{{ route('portal.logout') }}" style="display:inline">@csrf<button class="linklike" style="color:inherit">Salir</button></form>
            @else
                <a href="{{ route('portal.login') }}">Ingresar</a> · <a href="{{ route('portal.register') }}">Crear cuenta</a>
            @endif
            <br><a href="{{ route('portal.privacy') }}">Política de privacidad</a>
        </div>
    </div>
</footer>
<a href="#" class="pt-top" aria-label="Volver arriba"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5"/><path d="m5 12 7-7 7 7"/></svg></a>
<script>
// Avisos flotantes: desaparecen solos (5 s los de confirmación) o al pulsar ×
document.querySelectorAll('.pt-toast').forEach(t => {
    const hide = () => { t.classList.add('out'); setTimeout(() => t.remove(), 400); };
    const timer = setTimeout(hide, +t.dataset.ttl || 5000);
    t.querySelector('button').addEventListener('click', () => { clearTimeout(timer); hide(); });
});
(() => {
    const top = document.querySelector('.pt-top');
    const onScroll = () => top.classList.toggle('show', scrollY > 600);
    addEventListener('scroll', onScroll, {passive: true}); onScroll();
    top.addEventListener('click', e => { e.preventDefault(); scrollTo({top: 0, behavior: 'smooth'}); });
})();
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
