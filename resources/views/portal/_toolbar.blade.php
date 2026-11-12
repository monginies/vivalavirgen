<form class="pt-toolbar" method="get" action="{{ route('portal.home') }}">
    <label class="pt-search">
        <span>Buscar artículo, material o medida</span>
        <span class="pt-search-box"><input type="search" name="q" value="{{ request('q') }}" placeholder="Ej.: sombrilla, mimbre, 180 cm">
            <button aria-label="Buscar"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg></button></span>
    </label>
    <label class="pt-catsel">
        <span>Ver categoría</span>
        <select onchange="if (this.value) location.href = this.value">
            <option value="{{ route('portal.home') }}#colecciones">Todas las categorías</option>
            @foreach (\App\Models\Category::forPortal()->get() as $c)
                <option value="{{ route('portal.collection', $c->slug()) }}" @selected($current?->is($c))>{{ $c->name }}</option>
            @endforeach
        </select>
    </label>
</form>
