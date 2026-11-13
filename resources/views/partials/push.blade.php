{{-- Activar los avisos (notificaciones push) en este dispositivo --}}
@if (\App\Support\Push::configured())
<div class="card" id="push-card">
    <div class="head" style="margin-bottom:6px"><h2 style="margin:0">Avisos en este dispositivo</h2>
        <div class="actions">
            <button type="button" class="btn sm" id="push-on" hidden>Activar avisos</button>
            <button type="button" class="btn sec sm" id="push-test" hidden>Probar</button>
            <button type="button" class="btn sec sm" id="push-off" hidden>Desactivar</button>
        </div>
    </div>
    <p class="muted" id="push-msg" style="margin:0;font-size:14px">Recibe un aviso cuando se registre un cliente nuevo, cuando entre un pedido nuevo desde la web y cuando una pre-reserva vaya a caducar en menos de 24 horas.</p>
</div>
<script>
(() => {
    const key = @js(config('services.webpush.public'));
    const token = @js(csrf_token());
    const urls = {sub: @js(route('push.subscribe')), unsub: @js(route('push.unsubscribe')), test: @js(route('push.test'))};
    const on = document.getElementById('push-on'), off = document.getElementById('push-off'), test = document.getElementById('push-test'), msg = document.getElementById('push-msg');
    const post = (url, body) => fetch(url, {method: 'POST', headers: {'X-CSRF-TOKEN': token, 'Content-Type': 'application/json', 'Accept': 'application/json'}, body: JSON.stringify(body || {})}).then(r => r.json());
    const toKey = b64 => { const p = '='.repeat((4 - b64.length % 4) % 4), s = atob((b64 + p).replace(/-/g, '+').replace(/_/g, '/')); return Uint8Array.from([...s].map(c => c.charCodeAt(0))); };
    const ios = /iphone|ipad|ipod/i.test(navigator.userAgent), standalone = matchMedia('(display-mode: standalone)').matches || navigator.standalone;

    async function refresh() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            msg.textContent = ios && !standalone
                ? 'En iPhone, primero añade la app a la pantalla de inicio (Compartir → «Añadir a pantalla de inicio»), ábrela desde el icono y vuelve aquí para activar los avisos.'
                : 'Este navegador no permite avisos.';
            return;
        }
        const reg = await navigator.serviceWorker.ready;
        const sub = await reg.pushManager.getSubscription();
        on.hidden = !!sub; off.hidden = !sub; test.hidden = !sub;
        if (Notification.permission === 'denied') { on.hidden = true; msg.textContent = 'Los avisos están bloqueados para esta web. Actívalos en los ajustes del navegador o del móvil.'; }
        else if (sub) msg.textContent = 'Avisos activados en este dispositivo: clientes nuevos, pedidos nuevos de la web y pre-reservas que caducan en menos de 24 horas.';
    }

    on.addEventListener('click', async () => {
        try {
            if (await Notification.requestPermission() !== 'granted') return refresh();
            const reg = await navigator.serviceWorker.ready;
            const sub = await reg.pushManager.subscribe({userVisibleOnly: true, applicationServerKey: toKey(key)});
            await post(urls.sub, sub.toJSON());
            await post(urls.test);
        } catch (e) { msg.textContent = 'No se han podido activar: ' + (e.message || e); }
        refresh();
    });
    off.addEventListener('click', async () => {
        const sub = await (await navigator.serviceWorker.ready).pushManager.getSubscription();
        if (sub) { await post(urls.unsub, {endpoint: sub.endpoint}); await sub.unsubscribe(); }
        refresh();
    });
    test.addEventListener('click', async () => { const r = await post(urls.test); msg.textContent = r.sent ? 'Aviso de prueba enviado.' : 'No se ha podido enviar. Prueba a desactivar y activar de nuevo.'; });
    refresh();
})();
</script>
@endif
