<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Sin conexión · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="/css/app.css?v=48">
</head>
<body style="display:flex;min-height:100vh;align-items:center;justify-content:center;text-align:center;padding:24px">
    <div>
        <img src="/img/logo-96.png" alt="" width="72" height="72">
        <h1 style="margin-top:12px">Sin conexión</h1>
        <p class="muted">No hay internet en este momento. Lo que hayas guardado o escaneado ya está en la app.<br>Cuando vuelva la conexión, pulsa reintentar.</p>
        <button class="btn" onclick="location.reload()">Reintentar</button>
    </div>
</body>
</html>
