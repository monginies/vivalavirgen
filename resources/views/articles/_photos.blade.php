{{-- Fotos del artículo: galería y subida de varias a la vez (se reducen en el navegador antes de subirlas) --}}
<div class="card" id="photos-card">
    <div class="head" style="margin-bottom:12px"><h2 style="margin:0">Fotos <span class="muted" id="photo-count" style="font-family:var(--sans);font-weight:400;font-size:14px">{{ $article->photos->count() ?: '' }}</span></h2></div>
    <div class="gallery" id="gallery">
        @foreach ($article->photos as $p)
            <figure class="ph" data-id="{{ $p->id }}">
                <a href="{{ $p->url() }}" target="_blank"><img src="{{ $p->thumbUrl() }}" alt="" loading="lazy"></a>
                @if ($loop->first)<span class="badge green ph-main">Principal</span>@endif
                <figcaption>
                    @unless ($loop->first)
                        <form method="post" action="{{ route('article-photos.main', $p) }}">@csrf<button class="btn sec sm" title="Usar como foto principal">★</button></form>
                    @endunless
                    <form method="post" action="{{ route('article-photos.destroy', $p) }}" onsubmit="return confirm('¿Eliminar esta foto?')">@csrf @method('delete')<button class="btn danger sm" title="Eliminar">×</button></form>
                </figcaption>
            </figure>
        @endforeach
    </div>
    <form method="post" action="{{ route('article-photos.store', $article) }}" enctype="multipart/form-data" id="photo-form">
        @csrf
        <label class="drop" id="drop">
            <input type="file" name="photos[]" id="photo-input" accept="image/*" multiple>
            <b>Añadir fotos</b>
            <span class="muted">Pulsa para elegir varias a la vez, o arrástralas aquí</span>
        </label>
        <noscript><button class="btn" style="margin-top:10px">Subir</button></noscript>
        <div class="hint" id="photo-status"></div>
        @error('photos')<div class="err">{{ $message }}</div>@enderror
        @error('photos.*')<div class="err">{{ $message }}</div>@enderror
    </form>
</div>
<script>
(() => {
    const input = document.getElementById('photo-input'), drop = document.getElementById('drop');
    const status = document.getElementById('photo-status');
    const url = @json(route('article-photos.store', $article));
    const token = document.querySelector('#photo-form input[name=_token]').value;
    const MAX = 1600;

    // Reduce la foto a 1600 px en JPEG antes de subirla (más rápido desde el móvil y convierte HEIC en Safari)
    async function shrink(file) {
        try {
            const bmp = await createImageBitmap(file);
            const s = Math.min(1, MAX / Math.max(bmp.width, bmp.height));
            const c = document.createElement('canvas');
            c.width = Math.round(bmp.width * s); c.height = Math.round(bmp.height * s);
            const ctx = c.getContext('2d');
            ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, c.width, c.height);
            ctx.drawImage(bmp, 0, 0, c.width, c.height);
            const blob = await new Promise(r => c.toBlob(r, 'image/jpeg', 0.86));
            return blob ? new File([blob], file.name.replace(/\.\w+$/, '') + '.jpg', {type: 'image/jpeg'}) : file;
        } catch (e) { return file; }
    }

    async function upload(files) {
        files = [...files].filter(f => f.type.startsWith('image/') || /\.(heic|heif)$/i.test(f.name));
        if (!files.length) return;
        let ok = 0, errors = [];
        for (const [i, f] of files.entries()) {
            status.className = 'hint';
            status.textContent = `Subiendo ${i + 1} de ${files.length}…`;
            const fd = new FormData();
            fd.append('_token', token);
            fd.append('photos[]', await shrink(f));
            try {
                const res = await fetch(url, {method: 'POST', body: fd, headers: {Accept: 'application/json'}});
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || 'Error al subir');
                ok++;
            } catch (e) { errors.push(f.name + ': ' + e.message); }
        }
        if (errors.length) { status.className = 'err'; status.innerHTML = errors.map(e => e.replace(/</g, '&lt;')).join('<br>'); }
        if (ok) location.reload();
    }

    input.addEventListener('change', () => upload(input.files));
    ['dragenter', 'dragover'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.add('over'); }));
    ['dragleave', 'drop'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.remove('over'); }));
    drop.addEventListener('drop', e => upload(e.dataTransfer.files));
})();
</script>
