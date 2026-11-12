<form class="pt-toolbar" method="get" action="{{ route('portal.home') }}">
    <label class="pt-search" data-suggest="{{ route('portal.suggest') }}">
        <span class="pt-search-box"><input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar artículo, material o medida…" aria-label="Buscar artículo, material o medida"
            autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="pt-suggest">
            <button aria-label="Buscar"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg></button></span>
        <div class="pt-suggest" id="pt-suggest" role="listbox" hidden></div>
    </label>
    <label class="pt-catsel">
        <select aria-label="Ver categoría" onchange="if (this.value) location.href = this.value">
            <option value="{{ route('portal.home') }}#colecciones">Todas las categorías</option>
            @foreach (\App\Models\Category::forPortal()->get() as $c)
                <option value="{{ route('portal.collection', $c->slug()) }}" @selected($current?->is($c))>{{ $c->name }}</option>
            @endforeach
        </select>
    </label>
</form>

@once
@push('scripts')
<script>
(() => {
    const box = document.querySelector('[data-suggest]');
    if (!box) return;
    const input = box.querySelector('input'), list = box.querySelector('.pt-suggest');
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'})[c]);
    const words = q => q.trim().split(/\s+/).filter(Boolean).map(w => w.normalize('NFD').replace(/\p{M}/gu, '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
    const mark = (text, q) => {
        const ws = words(q); if (!ws.length) return esc(text);
        const plain = text.normalize('NFD').replace(/\p{M}/gu, '');
        if (plain.length !== text.length) return esc(text);
        const re = new RegExp('(' + ws.join('|') + ')', 'gi');
        let out = '', last = 0;
        plain.replace(re, (m, _g, i) => { out += esc(text.slice(last, i)) + '<mark>' + esc(text.slice(i, i + m.length)) + '</mark>'; last = i + m.length; });
        return out + esc(text.slice(last));
    };
    let timer, ctrl, active = -1, cache = {};
    const items = () => [...list.querySelectorAll('a')];
    const close = () => { list.hidden = true; input.setAttribute('aria-expanded', 'false'); active = -1; };
    const highlight = i => { const a = items(); a.forEach((el, k) => el.classList.toggle('on', k === i)); active = i; if (a[i]) a[i].scrollIntoView({block: 'nearest'}); };
    const render = (data, q) => {
        if (input.value.trim() !== q) return;
        let html = '';
        data.categories.forEach(c => { html += `<a role="option" class="pt-sg-cat" href="${esc(c.url)}"><span class="pt-sg-tag">Categoría</span>${mark(c.name, q)}</a>`; });
        data.articles.forEach(a => { html += `<a role="option" href="${esc(a.url)}">${a.img ? `<img src="${esc(a.img)}" alt="" loading="lazy">` : '<span class="pt-sg-noimg"></span>'}<span><b>${mark(a.name, q)}</b><small>${esc(a.meta)}</small></span></a>`; });
        html += data.articles.length || data.categories.length
            ? `<a role="option" class="pt-sg-all" href="${esc(data.all)}">Ver todos los resultados de «${esc(q)}» →</a>`
            : `<div class="pt-sg-empty">No encontramos nada con «${esc(q)}». Prueba con otra palabra o escríbenos y te ayudamos.</div>`;
        list.innerHTML = html; list.hidden = false; input.setAttribute('aria-expanded', 'true'); active = -1;
    };
    input.addEventListener('input', () => {
        clearTimeout(timer);
        const q = input.value.trim();
        if (q.length < 2) return close();
        if (cache[q]) return render(cache[q], q);
        timer = setTimeout(async () => {
            ctrl?.abort(); ctrl = new AbortController();
            try {
                const r = await fetch(box.dataset.suggest + '?q=' + encodeURIComponent(q), {headers: {Accept: 'application/json'}, signal: ctrl.signal});
                if (r.ok) render(cache[q] = await r.json(), q);
            } catch (e) {}
        }, 160);
    });
    input.addEventListener('keydown', e => {
        if (list.hidden) return;
        const a = items();
        if (e.key === 'ArrowDown') { e.preventDefault(); highlight(Math.min(active + 1, a.length - 1)); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); highlight(Math.max(active - 1, -1)); }
        else if (e.key === 'Enter' && active >= 0) { e.preventDefault(); location.href = a[active].href; }
        else if (e.key === 'Escape') close();
    });
    input.addEventListener('focus', () => { if (input.value.trim().length >= 2 && list.innerHTML) { list.hidden = false; input.setAttribute('aria-expanded', 'true'); } });
    document.addEventListener('click', e => { if (!box.contains(e.target)) close(); });
})();
</script>
@endpush
@endonce
