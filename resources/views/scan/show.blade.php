@extends('layouts.app')
@section('title', ($mode === 'salida' ? 'Salida ' : 'Entrada ').$order->number)
@section('content')
<div class="head">
    <div>
        <h1>{{ $mode === 'salida' ? 'Salida' : 'Entrada' }} · <span class="mono">{{ $order->number }}</span></h1>
        <div class="sub">{{ $order->client->name }} · {{ $mode === 'salida' ? 'entrega '.$order->start_date->format('d/m/Y').($order->delivery_time ? ' '.$order->delivery_time : '') : 'recogida '.$order->end_date->format('d/m/Y').($order->pickup_time ? ' '.$order->pickup_time : '') }}</div>
    </div>
    <div class="actions">
        <button type="button" class="btn sec" data-help-toggle="help-box" aria-expanded="false" data-open="Ayuda" data-close="Cerrar ayuda">Ayuda</button>
        <a class="btn sec" href="{{ route('expeditions.show', $order) }}">Volver a la expedición</a>
    </div>
</div>

<div id="help-box" hidden>@include('expeditions._help')</div>

<div class="card" style="padding:14px">
    <div id="scan-box" style="position:relative;max-width:520px;margin:0 auto;border-radius:10px;overflow:hidden;background:#222;aspect-ratio:4/3">
        <video id="scan-video" playsinline muted style="width:100%;height:100%;object-fit:cover;display:block"></video>
        <div style="position:absolute;inset:18%;border:3px solid #ffffffcc;border-radius:12px;pointer-events:none"></div>
        <button type="button" class="btn" id="scan-start" style="position:absolute;left:50%;top:50%;transform:translate(-50%,-50%)">Activar cámara</button>
    </div>
    <div id="scan-msg" class="flash" style="margin:12px auto 0;max-width:520px;display:none"></div>
    <form id="scan-manual" style="display:flex;gap:8px;max-width:520px;margin:12px auto 0">
        <input type="text" name="code" placeholder="O escribe el código" autocomplete="off" autocapitalize="characters">
        <button class="btn sec">Añadir</button>
    </form>
</div>

<div id="scan-list">@include('scan._list')</div>

<form method="post" action="{{ route('scan.finish', [$order, $mode]) }}" onsubmit="return confirm(@js($mode === 'salida' ? '¿Terminar la salida? El pedido pasará a «Entregado».' : '¿Terminar la entrada? El pedido pasará a «Devuelto».'))">
    @csrf
    <button class="btn" style="width:100%;justify-content:center;padding:14px">{{ $mode === 'salida' ? 'Terminar salida' : 'Terminar entrada' }}</button>
</form>

<script>
document.querySelectorAll('[data-help-toggle]').forEach(b => b.addEventListener('click', () => {
    const box = document.getElementById(b.dataset.helpToggle);
    box.hidden = !box.hidden;
    b.setAttribute('aria-expanded', String(!box.hidden));
    b.textContent = box.hidden ? b.dataset.open : b.dataset.close;
}));
</script>
<script src="{{ asset('js/jsQR.js') }}?v=1"></script>
<script>
(() => {
    const token = @js(csrf_token());
    const scanUrl = @js(route('scan.scan', [$order, $mode]));
    const unitUrl = @js(route('scan.update', [$order, $mode, '__UNIT__']));
    const video = document.getElementById('scan-video'), startBtn = document.getElementById('scan-start');
    const msg = document.getElementById('scan-msg'), list = document.getElementById('scan-list');
    const canvas = document.createElement('canvas'), ctx = canvas.getContext('2d', {willReadFrequently: true});
    const detector = ('BarcodeDetector' in window) ? new BarcodeDetector({formats: ['qr_code']}) : null;
    let last = '', lastAt = 0, busy = false, audio;

    function feedback(level, text) {
        msg.style.display = 'block';
        msg.className = 'flash ' + (level === 'ok' ? 'ok' : 'ko');
        msg.style.background = level === 'warn' ? '#f5ead6' : '';
        msg.textContent = text;
        if (navigator.vibrate) navigator.vibrate(level === 'ok' ? 80 : [80, 60, 80]);
        try {
            audio = audio || new (window.AudioContext || window.webkitAudioContext)();
            const o = audio.createOscillator(), g = audio.createGain();
            o.frequency.value = level === 'ok' ? 880 : 300; g.gain.value = .08;
            o.connect(g); g.connect(audio.destination); o.start(); o.stop(audio.currentTime + (level === 'ok' ? .12 : .3));
        } catch (e) {}
    }

    async function send(url, body) {
        busy = true;
        try {
            const r = await fetch(url, {method: 'POST', headers: {'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'Content-Type': 'application/json'}, body: JSON.stringify(body)});
            const j = await r.json();
            if (!r.ok) throw new Error(j.message || 'Error');
            feedback(j.level, j.message);
            list.innerHTML = j.html;
        } catch (e) {
            feedback('error', e.message || 'No se ha podido guardar. Revisa la conexión.');
        } finally { busy = false; }
    }

    function found(code) {
        const now = Date.now();
        if (busy || (code === last && now - lastAt < 2500)) return;
        last = code; lastAt = now;
        send(scanUrl, {code});
    }

    async function tick() {
        if (video.readyState >= 2 && !busy) {
            try {
                if (detector) {
                    const codes = await detector.detect(video);
                    if (codes.length) found(codes[0].rawValue);
                } else {
                    const w = video.videoWidth, h = video.videoHeight, s = Math.min(1, 640 / Math.max(w, h));
                    canvas.width = w * s; canvas.height = h * s;
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                    const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
                    const code = jsQR(img.data, img.width, img.height, {inversionAttempts: 'dontInvert'});
                    if (code && code.data) found(code.data);
                }
            } catch (e) {}
        }
        setTimeout(() => requestAnimationFrame(tick), 150);
    }

    startBtn.addEventListener('click', async () => {
        try {
            video.srcObject = await navigator.mediaDevices.getUserMedia({video: {facingMode: 'environment'}, audio: false});
            await video.play();
            startBtn.remove();
            tick();
        } catch (e) {
            feedback('error', 'No se puede usar la cámara: ' + (e.message || e.name) + '. Puedes escribir el código a mano.');
        }
    });

    document.getElementById('scan-manual').addEventListener('submit', e => {
        e.preventDefault();
        const input = e.target.code;
        if (input.value.trim()) { send(scanUrl, {code: input.value.trim()}); input.value = ''; }
    });

    list.addEventListener('click', e => {
        const b = e.target.closest('button[data-unit]');
        if (!b) return;
        send(unitUrl.replace('__UNIT__', b.dataset.unit), b.dataset.status ? {status: b.dataset.status} : {});
    });
})();
</script>
@endsection
