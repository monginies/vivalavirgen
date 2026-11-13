@extends('layouts.app')
@section('title', 'Cómo dar salida y entrada')
@section('content')
<style>
    .help { max-width: 760px; }
    .help h2 { margin-top: 4px; }
    .help ol, .help ul { padding-left: 22px; margin: 8px 0 0; }
    .help li { margin-bottom: 8px; }
    .help .step { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 50%; background: var(--sage-deep); color: #fff; font-weight: 700; font-size: 14px; margin-right: 8px; }
    .help dt { font-weight: 600; margin-top: 12px; }
    .help dd { margin: 2px 0 0; color: var(--ink); }
</style>
<div class="help">
<div class="head">
    <div><h1>Cómo dar salida y entrada</h1><div class="sub">Guía rápida para el equipo de logística</div></div>
    <a class="btn sec" href="{{ route('expeditions.index') }}">Ir a expediciones</a>
</div>

<div class="card">
    <h2>Antes de empezar</h2>
    <ul>
        <li>En <b>Expediciones</b> aparecen los pedidos ya pagados: <span class="badge amber">Pendiente de salir</span>, <span class="badge blue">Fuera</span> (en el evento) y <span class="badge green">Devuelta</span>.</li>
        <li>Pulsa el número del pedido para ver la <b>ficha</b>: fechas y horas, lugar, persona de contacto (pulsa el teléfono para llamar), notas y la lista de material.</li>
        <li>Con <b>Hojas PDF</b> descargas la hoja de entrega y la de recogida para imprimirlas. Lleva la hoja de entrega en el camión para que el cliente la firme.</li>
        <li>Cada pieza lleva una <b>etiqueta QR</b> con su código (por ejemplo <span class="mono">SAL-0002-003</span>). Es lo que vas a escanear.</li>
        <li>Usa el <b>móvil</b> con la cámara trasera. La primera vez, el navegador te pedirá permiso para usar la cámara: pulsa <b>Permitir</b>.</li>
    </ul>
</div>

<div class="card">
    <h2>Dar salida (al cargar en la nave)</h2>
    <p><span class="step">1</span>En Expediciones, busca el pedido <span class="badge amber">Pendiente de salir</span> y pulsa <b>Dar salida</b>.</p>
    <p><span class="step">2</span>Pulsa <b>Activar cámara</b> y enfoca la etiqueta QR de cada pieza dentro del recuadro blanco. No hace falta pulsar nada más.</p>
    <p><span class="step">3</span>Con cada lectura el móvil suena y vibra, y aparece un mensaje:</p>
    <ul>
        <li><span class="badge green">verde</span>: correcto, la pieza queda cargada.</li>
        <li><span class="badge amber">ámbar</span>: aviso. Ya estaba escaneada, sobra o no es de este pedido. Revisa si tiene que ir.</li>
        <li><span class="badge red">rojo</span>: no se puede sacar (está en otro pedido, en reparación o de baja). Déjala en la nave y avisa a oficina.</li>
    </ul>
    <p><span class="step">4</span>Abajo verás, por artículo, cuántas lleva el pedido y cuántas llevas. Cuando todo esté en verde (✓), está completo. Los <b>salones y conjuntos</b> aparecen ya separados en sus piezas.</p>
    <p><span class="step">5</span>Si has escaneado una pieza por error, búscala en «Unidades escaneadas» y pulsa <b>Quitar</b>.</p>
    <p><span class="step">6</span>Al terminar de cargar, pulsa <b>Terminar salida</b>. El pedido pasa a <span class="badge blue">Fuera</span>. Si falta algo, la app te avisa, pero te deja terminar: avisa a oficina.</p>
</div>

<div class="card">
    <h2>En el evento</h2>
    <ul>
        <li>Al entregar, pide al cliente que firme la <b>hoja de entrega</b> (fecha y hora, nombre y firma).</li>
        <li>Al recoger, que firme la <b>hoja de recogida</b>. Marca en las casillas lo que vas cargando.</li>
    </ul>
</div>

<div class="card">
    <h2>Dar entrada (al volver a la nave)</h2>
    <p><span class="step">1</span>En Expediciones, busca el pedido <span class="badge blue">Fuera</span> y pulsa <b>Dar entrada</b>.</p>
    <p><span class="step">2</span>Pulsa <b>Activar cámara</b> y escanea cada pieza al descargarla. Cada pieza escaneada queda como <b>Devuelta</b> y vuelve a estar disponible para alquilar.</p>
    <p><span class="step">3</span>Si una pieza vuelve <b>rota o necesita arreglo</b>, pulsa <b>Rota</b> junto a ella. Queda «En reparación» y no se podrá alquilar hasta que oficina la revise.</p>
    <p><span class="step">4</span>Si una pieza <b>no vuelve</b>, pulsa <b>Perdida</b>. Oficina la descontará de la fianza.</p>
    <p><span class="step">5</span>Si te equivocas, vuelve a pulsar el mismo botón para dejar la pieza como pendiente.</p>
    <p><span class="step">6</span>Al terminar, pulsa <b>Terminar entrada</b>. El pedido pasa a <span class="badge green">Devuelta</span>. Si quedan piezas sin volver, la app te lo indica.</p>
</div>

<div class="card">
    <h2>Si algo falla</h2>
    <dl>
        <dt>La etiqueta está rota o no se lee</dt>
        <dd>Escribe el código que lleva impreso junto al QR (por ejemplo <span class="mono">SAL-0002-003</span>) en la casilla «O escribe el código» y pulsa <b>Añadir</b>.</dd>
        <dt>La cámara no se abre</dt>
        <dd>Revisa que el navegador tenga permiso para usar la cámara en vivalavirgen.net (en los ajustes del navegador). Si no, escribe los códigos a mano.</dd>
        <dt>Escaneo la etiqueta con la cámara normal del móvil</dt>
        <dd>Te llevará a Expediciones. Para dar salida o entrada hay que escanear desde la pantalla de <b>Dar salida</b> o <b>Dar entrada</b> del pedido.</dd>
        <dt>Se ha cortado la conexión</dt>
        <dd>Lo escaneado hasta ese momento queda guardado. Vuelve a abrir el pedido y continúa.</dd>
        <dt>Me olvidé una pieza al dar salida</dt>
        <dd>Abre la expedición (estará «Fuera») y pulsa <b>Seguir dando salida</b> para añadirla.</dd>
    </dl>
</div>
</div>
@endsection
