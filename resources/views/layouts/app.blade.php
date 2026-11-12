<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Inicio') · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=27">
    <link rel="icon" type="image/png" href="{{ asset('img/favicon-32.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('img/apple-touch-icon.png') }}">
    <meta name="theme-color" content="#9a9b88">
</head>
<body>
@auth
<header class="topbar">
    <a class="brand" href="{{ route('dashboard') }}" title="{{ config('app.name') }} · Inicio"><img src="{{ asset('img/logo-96.png') }}" alt="{{ config('app.name') }}"></a>
    @php
        $menu = [
            ['Inicio', 'home', 'dashboard', ['dashboard']],
            ['Pedidos', 'orders', null, ['orders.*', 'calendar'], [
                ['Pedidos', 'orders', 'orders.index', ['orders.*']],
                ['Calendario', 'calendar', 'calendar', ['calendar']],
            ]],
            ['Artículos', 'articles', null, ['articles.*', 'units.*', 'categories.*', 'availability'], [
                ['Artículos', 'articles', 'articles.index', ['articles.*', 'units.*']],
                ['Categorías', 'categories', 'categories.index', ['categories.*']],
                ['Disponibilidad', 'availability', 'availability', ['availability']],
            ]],
            ['Clientes', 'clients', null, ['clients.*', 'venues.*'], [
                ['Clientes', 'clients', 'clients.index', ['clients.*']],
                ['Fincas', 'venues', 'venues.index', ['venues.*']],
            ]],
        ];
        if (auth()->user()->is_superadmin) {
            $menu[] = ['Administración', 'settings', null, ['users.*', 'settings.*', 'client-categories.*'], [
                ['Usuarios', 'users', 'users.index', ['users.*']],
                ['Tipos de cliente', 'clients', 'client-categories.index', ['client-categories.*']],
                ['Ajustes', 'settings', 'settings.edit', ['settings.*']],
            ]];
        }
    @endphp
    <nav id="mainnav">
        @foreach ($menu as $item)
            @php([$label, $icon, $route, $active] = $item)
            @if (empty($item[4]))
                <a class="top @if (request()->routeIs(...$active)) on @endif" href="{{ route($route) }}" title="{{ $label }}">@include('partials.icon', ['i' => $icon])<span>{{ $label }}</span></a>
            @else
                <details class="menu @if (request()->routeIs(...$active)) on @endif">
                    <summary class="top" title="{{ $label }}">@include('partials.icon', ['i' => $icon])<span>{{ $label }}</span><svg class="chev" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary>
                    <div class="sub">
                        @foreach ($item[4] as [$subLabel, $subIcon, $subRoute, $subActive])
                            <a href="{{ route($subRoute) }}" @class(['on' => request()->routeIs(...$subActive)])>@include('partials.icon', ['i' => $subIcon]){{ $subLabel }}</a>
                        @endforeach
                    </div>
                </details>
            @endif
        @endforeach
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
<script>
// Código postal → población (campos con data-cp-city="id-del-campo-población")
document.querySelectorAll('input[data-cp-city]').forEach(cp => {
    const city = document.getElementById(cp.dataset.cpCity);
    if (!city) return;
    const list = document.createElement('datalist');
    list.id = city.id + '-places';
    city.setAttribute('list', list.id);
    city.after(list);
    const hint = document.createElement('div');
    hint.className = 'hint';
    cp.parentNode.appendChild(hint);
    let auto = '';
    cp.addEventListener('input', async () => {
        const code = cp.value.replace(/\D/g, '');
        if (code.length !== 5) { hint.textContent = ''; return; }
        try {
            const data = await (await fetch(@json(url('app/postal-codes')) + '/' + code, {headers: {Accept: 'application/json'}})).json();
            if (!data.found) { hint.className = 'err'; hint.textContent = 'CP no encontrado'; return; }
            hint.className = 'hint';
            hint.textContent = data.province || '';
            list.innerHTML = data.places.map(p => '<option value="' + p.replace(/"/g, '&quot;') + '">').join('');
            if (!city.value.trim() || city.value === auto) {
                city.value = auto = data.places.length === 1 ? data.places[0] : (data.municipality || data.places[0]);
            }
        } catch (e) {}
    });
});

// Menú desplegable: se abre al pulsar (y al pasar el ratón en escritorio); solo uno abierto a la vez
(() => {
    const menus = [...document.querySelectorAll('#mainnav details.menu')];
    const closeAll = except => menus.forEach(m => { if (m !== except) m.open = false; });
    menus.forEach(m => {
        m.addEventListener('toggle', () => { if (m.open) closeAll(m); });
        if (matchMedia('(hover: hover) and (min-width: 641px)').matches) {
            let t;
            m.addEventListener('mouseenter', () => { clearTimeout(t); m.open = true; });
            m.addEventListener('mouseleave', () => { t = setTimeout(() => { m.open = false; }, 180); });
        }
    });
    document.addEventListener('click', e => { if (!e.target.closest('#mainnav details.menu')) closeAll(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeAll(); });
})();
</script>
</body>
</html>
